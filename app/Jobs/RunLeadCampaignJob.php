<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\LeadGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunLeadCampaignJob implements ShouldQueue
{
    use Queueable;
    public int $tries = 2;
    public int $timeout = 180;
    public function __construct(public int $campaignId) {}
    public function handle(LeadGenerationService $service): void
    {
        Log::channel('campaigns')->info('Campaign job started.', [
            'campaign_id' => $this->campaignId,
            'connection' => $this->connection,
            'php_binary' => PHP_BINARY,
        ]);

        $service->run(Campaign::findOrFail($this->campaignId));
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('campaigns')->error('Campaign job failed outside the generation service.', [
            'campaign_id' => $this->campaignId,
            'error' => $exception?->getMessage(),
        ]);

        Campaign::whereKey($this->campaignId)->update([
            'status' => 'failed',
            'failure_reason' => $exception?->getMessage() ?: 'The campaign job failed before it could start.',
            'completed_at' => now(),
        ]);
    }
}
