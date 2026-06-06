<?php

namespace Tests\Feature;

use App\Jobs\RunLeadCampaignJob;
use App\Models\Campaign;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_runs_on_automatic_background_connection(): void
    {
        Queue::fake();
        config([
            'services.google_places.key' => 'test-key',
            'lead-generator.campaign_queue_connection' => 'background',
        ]);

        $user = User::factory()->create();
        $service = Service::create([
            'user_id' => $user->id,
            'service_name' => 'Website Development',
            'category' => 'Web Development',
        ]);
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'title' => 'Restaurants in Lahore',
            'city' => 'Lahore',
            'business_category' => 'Restaurant',
        ]);

        $this->actingAs($user)
            ->post(route('campaigns.run', $campaign))
            ->assertRedirect(route('campaigns.show', $campaign))
            ->assertSessionHas('success', 'Campaign started automatically. Live progress is shown below.');

        Queue::assertPushed(
            RunLeadCampaignJob::class,
            fn (RunLeadCampaignJob $job) => $job->campaignId === $campaign->id
                && $job->connection === 'background'
        );

        $this->assertNotNull($campaign->fresh()->started_at);
    }

    public function test_owner_can_poll_live_campaign_progress(): void
    {
        $user = User::factory()->create();
        $service = Service::create([
            'user_id' => $user->id,
            'service_name' => 'Website Development',
            'category' => 'Web Development',
        ]);
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'title' => 'Hotels in Murree',
            'city' => 'Murree',
            'business_category' => 'Hotel',
            'status' => 'running',
            'progress_percentage' => 65,
            'total_found' => 20,
            'total_saved' => 8,
            'hot_leads' => 3,
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('campaigns.status', $campaign))
            ->assertOk()
            ->assertJson([
                'status' => 'running',
                'status_label' => 'Running',
                'progress_percentage' => 65,
                'total_found' => 20,
                'total_saved' => 8,
                'hot_leads' => 3,
                'is_active' => true,
            ]);
    }

    public function test_running_campaign_cannot_be_dispatched_twice(): void
    {
        Queue::fake();
        config(['services.google_places.key' => 'test-key']);

        $user = User::factory()->create();
        $service = Service::create([
            'user_id' => $user->id,
            'service_name' => 'Website Development',
            'category' => 'Web Development',
        ]);
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'title' => 'Clinics in Islamabad',
            'city' => 'Islamabad',
            'business_category' => 'Clinic',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('campaigns.run', $campaign))
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }
}
