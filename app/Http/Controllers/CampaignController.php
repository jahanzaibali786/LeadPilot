<?php

namespace App\Http\Controllers;

use App\Jobs\RunLeadCampaignJob;
use App\Models\Campaign;
use App\Models\Service;
use Illuminate\Http\Request;
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
            'services' => Service::where('user_id', auth()->id())->where('is_active', true)->get(),
            'countries' => $this->countries(),
            'googleMapsBrowserKey' => config('services.google_places.browser_key'),
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

        Campaign::create($data + [
            'user_id' => auth()->id(),
            'source_type' => 'google_places',
            'only_without_website' => $request->boolean('only_without_website'),
            'only_with_phone' => $request->boolean('only_with_phone'),
            'status' => 'draft',
        ]);

        return back()->with('success', 'Campaign created.');
    }

    public function show(Campaign $campaign)
    {
        $this->own($campaign);

        return view('campaigns.show', [
            'campaign' => $campaign->load('service'),
            'leads' => $campaign->leads()->latest()->paginate(20),
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

    public function run(Request $request, Campaign $campaign)
    {
        $this->own($campaign);
        abort_if(! config('services.google_places.key'), 422, 'Add GOOGLE_PLACES_API_KEY before running a campaign.');
        abort_if(
            $campaign->status === 'running' || ($campaign->status === 'pending' && $campaign->started_at),
            422,
            'This campaign is already queued or running.'
        );

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

        $connection = config('lead-generator.campaign_queue_connection', 'deferred');
        Log::channel('campaigns')->info('Campaign dispatch requested.', [
            'campaign_id' => $campaign->id,
            'user_id' => auth()->id(),
            'connection' => $connection,
            'request_url' => $request->fullUrl(),
        ]);

        RunLeadCampaignJob::dispatch($campaign->id)
            ->onConnection($connection);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Campaign started automatically. Live progress is shown below.',
                'show_url' => route('campaigns.show', $campaign),
                'status_url' => route('campaigns.status', $campaign),
                'campaign' => $this->statusPayload($campaign->fresh()),
            ], 202);
        }

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign started automatically. Live progress is shown below.');
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
            'valid_leads' => $campaign->valid_leads,
            'hot_leads' => $campaign->hot_leads,
            'warm_leads' => $campaign->warm_leads,
            'cold_leads' => $campaign->cold_leads,
            'failed_requests' => $campaign->failed_requests,
            'failure_reason' => $campaign->failure_reason,
            'started_at' => optional($campaign->started_at)->toIso8601String(),
            'completed_at' => optional($campaign->completed_at)->toIso8601String(),
            'is_active' => in_array($campaign->status, ['pending', 'running'], true),
        ];
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

        $message = "The automatic campaign runner did not start within {$staleAfter} seconds. Check storage/logs/campaigns.log and storage/logs/laravel.log.";

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
