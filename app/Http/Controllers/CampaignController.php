<?php

namespace App\Http\Controllers;

use App\Jobs\RunLeadCampaignJob;
use App\Models\ApiLog;
use App\Models\Campaign;
use App\Models\Service;
use App\Services\GooglePlacesCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::where('user_id', auth()->id())
            ->with('service')
            ->latest()
            ->paginate(15);

        $campaigns->getCollection()->each(fn (Campaign $campaign) => $this->failStalePendingCampaign($campaign));

        return view('campaigns.index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create()
    {
        return view('campaigns.create', [
            'services' => Service::where('user_id', auth()->id())->where('is_active', true)->get(),
            'countries' => $this->countries(),
        ]);
    }

    public function store(Request $request)
    {
        $countries = $this->countries();
        $data = $request->validate([
            'title' => 'required',
            'service_id' => 'required|exists:services,id',
            'country' => 'required|string|max:100',
            'country_code' => ['required', 'string', 'size:2', Rule::in(array_keys($countries))],
            'province' => 'nullable',
            'city' => 'required',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:500|max:50000',
            'business_category' => 'required',
            'keyword' => 'nullable',
            'minimum_rating' => 'numeric|min:0|max:5',
            'minimum_reviews' => 'integer|min:0',
            'required_leads' => 'integer|min:1|max:500',
        ]);

        abort_unless(Service::where('user_id', auth()->id())->whereKey($data['service_id'])->exists(), 403);
        $data['country_code'] = strtoupper($data['country_code']);
        $data['country'] = $countries[$data['country_code']];

        $campaign = Campaign::create($data + [
            'user_id' => auth()->id(),
            'source_type' => 'google_places',
            'only_without_website' => $request->boolean('only_without_website'),
            'only_with_phone' => $request->boolean('only_with_phone'),
            'status' => 'draft',
        ]);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign created.');
    }

    public function edit(Campaign $campaign)
    {
        $this->own($campaign);
        abort_if(in_array($campaign->status, ['pending', 'running'], true), 422, 'An active campaign cannot be edited. Cancel it first.');

        return view('campaigns.create', [
            'campaign' => $campaign,
            'services' => Service::where('user_id', auth()->id())
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $campaign->service_id))
                ->get(),
            'countries' => $this->countries(),
        ]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->own($campaign);
        abort_if(in_array($campaign->status, ['pending', 'running'], true), 422, 'An active campaign cannot be edited. Cancel it first.');

        $countries = $this->countries();
        $data = $request->validate([
            'title' => 'required',
            'service_id' => 'required|exists:services,id',
            'country' => 'required|string|max:100',
            'country_code' => ['required', 'string', 'size:2', Rule::in(array_keys($countries))],
            'province' => 'nullable',
            'city' => 'required',
            'latitude' => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude' => 'nullable|required_with:latitude|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:500|max:50000',
            'business_category' => 'required',
            'keyword' => 'nullable',
            'minimum_rating' => 'numeric|min:0|max:5',
            'minimum_reviews' => 'integer|min:0',
            'required_leads' => 'integer|min:1|max:500',
        ]);

        abort_unless(Service::where('user_id', auth()->id())->whereKey($data['service_id'])->exists(), 403);
        $data['country_code'] = strtoupper($data['country_code']);
        $data['country'] = $countries[$data['country_code']];

        $campaign->update($data + [
            'only_without_website' => $request->boolean('only_without_website'),
            'only_with_phone' => $request->boolean('only_with_phone'),
        ]);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function show(Campaign $campaign)
    {
        $this->own($campaign);

        return view('campaigns.show', [
            'campaign' => $campaign->load('service'),
            'leads' => $campaign->leads()->latest()->paginate(20),
            'apiRequests' => ApiLog::where('campaign_id', $campaign->id)->count(),
        ]);
    }

    public function status(Campaign $campaign)
    {
        $this->own($campaign);
        $campaign->refresh();
        $this->failStalePendingCampaign($campaign);
        $campaign->refresh();

        return response()->json($this->statusPayload($campaign));
    }

    public function run(Request $request, Campaign $campaign, GooglePlacesCredentialService $credentials)
    {
        $this->own($campaign);
        abort_if(! $credentials->forUser($campaign->user_id), 422, 'Add a Google Places API key in Settings before running a campaign.');

        $cancelledCampaigns = $this->cancelActiveCampaignsBeforeRun($campaign);
        $clearedJobs = $this->clearPendingCampaignJobs();

        $qualityCounts = $campaign->leads()
            ->selectRaw('lead_quality, count(*) total')
            ->groupBy('lead_quality')
            ->pluck('total', 'lead_quality');
        $savedCount = $qualityCounts->sum();

        $campaign->update([
            'status' => 'pending',
            'progress_percentage' => 0,
            'failure_reason' => null,
            'started_at' => now(),
            'completed_at' => null,
            'total_found' => 0,
            'duplicates_removed' => 0,
            'total_saved' => $savedCount,
            'valid_leads' => $savedCount,
            'hot_leads' => (int) ($qualityCounts['Hot'] ?? 0),
            'warm_leads' => (int) ($qualityCounts['Warm'] ?? 0),
            'cold_leads' => (int) ($qualityCounts['Cold'] ?? 0),
            'failed_requests' => 0,
        ]);

        $connection = config('lead-generator.campaign_queue_connection', 'database');
        Log::channel('campaigns')->info('Campaign dispatch requested.', [
            'campaign_id' => $campaign->id,
            'user_id' => auth()->id(),
            'connection' => $connection,
            'cancelled_active_campaigns' => $cancelledCampaigns,
            'cleared_pending_jobs' => $clearedJobs,
            'request_url' => $request->fullUrl(),
        ]);

        RunLeadCampaignJob::dispatch($campaign->id)
            ->onConnection($connection);

        $exitCode = $this->runCampaignWorker();
        $campaign->refresh();

        Log::channel('campaigns')->info('Campaign worker command finished.', [
            'campaign_id' => $campaign->id,
            'exit_code' => $exitCode,
            'status' => $campaign->status,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Campaign worker started automatically. Live progress is shown below.',
                'show_url' => route('campaigns.show', $campaign),
                'status_url' => route('campaigns.status', $campaign),
                'campaign' => $this->statusPayload($campaign),
            ], 202);
        }

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign worker started automatically. Live progress is shown below.');
    }

    public function cancel(Campaign $campaign)
    {
        $this->own($campaign);
        $campaign->update(['status' => 'cancelled']);

        return back()->with('success', 'Campaign cancelled.');
    }

    public function destroy(Campaign $campaign)
    {
        $this->own($campaign);
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted.');
    }

    private function own(Campaign $campaign): void
    {
        abort_unless($campaign->user_id === auth()->id() || auth()->user()->hasRole('Super Admin'), 403);
    }

    private function statusPayload(Campaign $campaign): array
    {
        return [
            'status' => $campaign->status,
            'status_label' => ucfirst($campaign->status),
            'progress_percentage' => $campaign->progress_percentage,
            'total_found' => $campaign->total_found,
            'total_saved' => $campaign->total_saved,
            'duplicates_removed' => $campaign->duplicates_removed,
            'skipped_filters' => max(0, $campaign->total_found - $campaign->total_saved - $campaign->duplicates_removed),
            'valid_leads' => $campaign->valid_leads,
            'hot_leads' => $campaign->hot_leads,
            'warm_leads' => $campaign->warm_leads,
            'cold_leads' => $campaign->cold_leads,
            'failed_requests' => $campaign->failed_requests,
            'api_requests' => ApiLog::where('campaign_id', $campaign->id)
                ->where('created_at', '>=', $campaign->started_at ?: now()->subDay())
                ->count(),
            'failure_reason' => $campaign->failure_reason,
            'started_at' => optional($campaign->started_at)->toIso8601String(),
            'completed_at' => optional($campaign->completed_at)->toIso8601String(),
            'is_active' => in_array($campaign->status, ['pending', 'running'], true),
            'recent_leads' => $campaign->leads()->latest()->take(20)->get()->map(fn ($lead) => [
                'id' => $lead->id,
                'business_name' => $lead->business_name,
                'business_category' => $lead->business_category,
                'formatted_phone' => $lead->formatted_phone,
                'rating' => $lead->rating,
                'total_reviews' => $lead->total_reviews,
                'website_status' => $lead->website_status,
                'website' => $lead->website,
                'has_website' => (bool) $lead->has_website,
                'lead_score' => $lead->lead_score,
                'lead_quality' => $lead->lead_quality,
                'board_status' => $lead->board_status,
                'url' => route('leads.show', $lead),
            ])->values(),
        ];
    }


    private function cancelActiveCampaignsBeforeRun(Campaign $campaign): int
    {
        return Campaign::where('user_id', $campaign->user_id)
            ->whereIn('status', ['pending', 'running'])
            ->whereKeyNot($campaign->id)
            ->update([
                'status' => 'cancelled',
                'failure_reason' => 'Cancelled automatically because a new campaign run was started.',
                'completed_at' => now(),
            ]);
    }

    private function clearPendingCampaignJobs(): int
    {
        if (config('lead-generator.campaign_queue_connection', 'database') !== 'database') {
            return 0;
        }

        $connection = config('queue.connections.database.connection');
        $table = config('queue.connections.database.table', 'jobs');
        $queue = config('queue.connections.database.queue', 'default');

        return DB::connection($connection)
            ->table($table)
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('payload', 'like', '%RunLeadCampaignJob%')
            ->delete();
    }

    private function runCampaignWorker(): int
    {
        return Artisan::call('campaigns:work');
    }

    private function countries(): array
    {
        static $countries;
        if ($countries !== null) {
            return $countries;
        }

        $countries = [];

        foreach (range('A', 'Z') as $first) {
            foreach (range('A', 'Z') as $second) {
                $code = $first.$second;
                $name = locale_get_display_region('und_'.$code, 'en');
                if ($name && $name !== $code && ! str_starts_with($name, 'Unknown Region')) {
                    $countries[$code] = $name;
                }
            }
        }

        asort($countries);

        return $countries;
    }

    private function failStalePendingCampaign(Campaign $campaign): void
    {
        $staleAfter = config('lead-generator.stale_pending_seconds', 30);

        if (
            $campaign->status !== 'pending'
            || ! $campaign->started_at
            || $campaign->progress_percentage > 0
            || $campaign->started_at->gt(now()->subSeconds($staleAfter))
        ) {
            return;
        }

        $message = "The campaign queue worker did not start within {$staleAfter} seconds. Run php artisan campaigns:work and check storage/logs/campaigns.log.";

        $campaign->update([
            'status' => 'failed',
            'failure_reason' => $message,
            'completed_at' => now(),
        ]);

        Log::channel('campaigns')->error('Campaign launch became stale before worker heartbeat.', [
            'campaign_id' => $campaign->id,
            'started_at' => $campaign->started_at,
            'stale_after_seconds' => $staleAfter,
        ]);
    }
}
