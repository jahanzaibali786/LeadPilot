<?php

namespace App\Http\Controllers;

use App\Jobs\RunLeadCampaignJob;
use App\Models\Campaign;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required',
            'service_id' => 'required|exists:services,id',
            'province' => 'nullable',
            'city' => 'required',
            'business_category' => 'required',
            'keyword' => 'nullable',
            'minimum_rating' => 'numeric|min:0|max:5',
            'minimum_reviews' => 'integer|min:0',
            'required_leads' => 'integer|min:1|max:500',
        ]);

        abort_unless(Service::where('user_id', auth()->id())->whereKey($data['service_id'])->exists(), 403);

        Campaign::create($data + [
            'user_id' => auth()->id(),
            'source_type' => 'google_places',
            'country' => 'Pakistan',
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

        return response()->json([
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
        ]);
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

        $campaign->update([
            'status' => 'pending',
            'progress_percentage' => 0,
            'failure_reason' => null,
            'started_at' => now(),
            'completed_at' => null,
            'total_found' => 0,
            'total_saved' => 0,
            'duplicates_removed' => 0,
            'valid_leads' => 0,
            'hot_leads' => 0,
            'warm_leads' => 0,
            'cold_leads' => 0,
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
