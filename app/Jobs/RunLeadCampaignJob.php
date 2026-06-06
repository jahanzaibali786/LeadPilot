<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\LeadGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunLeadCampaignJob implements ShouldQueue
{
    use Queueable;
    public int $tries = 2;
    public int $timeout = 180;
    public function __construct(public int $campaignId) {}
    public function handle(LeadGenerationService $service): void { $service->run(Campaign::findOrFail($this->campaignId)); }
}
