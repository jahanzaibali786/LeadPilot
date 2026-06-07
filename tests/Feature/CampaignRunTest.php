<?php

namespace Tests\Feature;

use App\Jobs\RunLeadCampaignJob;
use App\Models\Campaign;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_runs_on_automatic_database_connection(): void
    {
        Queue::fake();
        config([
            'services.google_places.key' => 'test-key',
            'lead-generator.campaign_queue_connection' => 'database',
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
            ->postJson(route('campaigns.run', $campaign))
            ->assertStatus(202)
            ->assertJson([
                'message' => 'Campaign worker started automatically. Live progress is shown below.',
                'show_url' => route('campaigns.show', $campaign),
                'campaign' => [
                    'status' => 'pending',
                    'is_active' => true,
                ],
            ]);

        Queue::assertPushed(
            RunLeadCampaignJob::class,
            fn (RunLeadCampaignJob $job) => $job->campaignId === $campaign->id
                && $job->connection === 'database'
        );

        $this->assertNotNull($campaign->fresh()->started_at);
    }

    public function test_new_campaign_is_draft_and_not_running(): void
    {
        $user = User::factory()->create();
        $service = Service::create([
            'user_id' => $user->id,
            'service_name' => 'Website Development',
            'category' => 'Web Development',
        ]);

        $this->actingAs($user)->post(route('campaigns.store'), [
            'title' => 'Draft campaign',
            'service_id' => $service->id,
            'country' => 'Canada',
            'country_code' => 'CA',
            'province' => 'Ontario',
            'city' => 'Toronto',
            'latitude' => 43.6532,
            'longitude' => -79.3832,
            'radius_meters' => 15000,
            'business_category' => 'Restaurant',
            'minimum_rating' => 3.5,
            'minimum_reviews' => 10,
            'required_leads' => 20,
        ])->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'title' => 'Draft campaign',
            'status' => 'draft',
            'progress_percentage' => 0,
            'country' => 'Canada',
            'country_code' => 'CA',
            'city' => 'Toronto',
            'radius_meters' => 15000,
        ]);
    }

    public function test_stale_pending_campaign_is_marked_failed_with_log_guidance(): void
    {
        config(['lead-generator.stale_pending_seconds' => 30]);
        $user = User::factory()->create();
        $service = Service::create([
            'user_id' => $user->id,
            'service_name' => 'Website Development',
            'category' => 'Web Development',
        ]);
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'title' => 'Stale campaign',
            'city' => 'Lahore',
            'business_category' => 'Restaurant',
            'status' => 'pending',
            'progress_percentage' => 0,
            'started_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->getJson(route('campaigns.status', $campaign))
            ->assertOk()
            ->assertJson([
                'status' => 'failed',
                'is_active' => false,
            ]);

        $this->assertStringContainsString(
            'storage/logs/campaigns.log',
            $campaign->fresh()->failure_reason
        );
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

    public function test_running_campaign_can_be_restarted(): void
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
            'started_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($user)
            ->postJson(route('campaigns.run', $campaign))
            ->assertStatus(202)
            ->assertJsonPath('campaign.status', 'pending');

        Queue::assertPushed(
            RunLeadCampaignJob::class,
            fn (RunLeadCampaignJob $job) => $job->campaignId === $campaign->id
        );

        $this->assertNotNull($campaign->fresh()->started_at);
    }

    public function test_starting_campaign_cancels_other_active_campaigns_and_clears_pending_jobs(): void
    {
        Queue::fake();
        config(['services.google_places.key' => 'test-key']);

        $user = User::factory()->create();
        $service = Service::create([
            'user_id' => $user->id,
            'service_name' => 'Website Development',
            'category' => 'Web Development',
        ]);
        $previousCampaign = Campaign::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'title' => 'Old campaign',
            'city' => 'Karachi',
            'business_category' => 'Agency',
            'status' => 'running',
            'started_at' => now()->subMinutes(10),
        ]);
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'title' => 'Fresh campaign',
            'city' => 'Lahore',
            'business_category' => 'Restaurant',
            'status' => 'draft',
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{"displayName":"App\\Jobs\\RunLeadCampaignJob"}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->postJson(route('campaigns.run', $campaign))
            ->assertStatus(202)
            ->assertJsonPath('campaign.status', 'pending');

        $this->assertSame('cancelled', $previousCampaign->fresh()->status);
        $this->assertDatabaseMissing('jobs', [
            'queue' => 'default',
            'payload' => '{"displayName":"App\\Jobs\\RunLeadCampaignJob"}',
        ]);
        Queue::assertPushed(
            RunLeadCampaignJob::class,
            fn (RunLeadCampaignJob $job) => $job->campaignId === $campaign->id
        );
    }

    public function test_campaign_can_be_edited(): void
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
            'title' => 'Old campaign',
            'country' => 'Pakistan',
            'country_code' => 'PK',
            'city' => 'Lahore',
            'radius_meters' => 10000,
            'business_category' => 'Restaurant',
            'only_without_website' => true,
            'only_with_phone' => true,
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->get(route('campaigns.edit', $campaign))
            ->assertOk()
            ->assertSee('Edit campaign');

        $this->actingAs($user)->put(route('campaigns.update', $campaign), [
            'title' => 'Updated Toronto campaign',
            'service_id' => $service->id,
            'country' => 'Canada',
            'country_code' => 'CA',
            'province' => 'Ontario',
            'city' => 'Toronto',
            'latitude' => 43.6532,
            'longitude' => -79.3832,
            'radius_meters' => 20000,
            'business_category' => 'Dentist',
            'keyword' => 'family dentist',
            'minimum_rating' => 4,
            'minimum_reviews' => 25,
            'required_leads' => 75,
        ])->assertRedirect(route('campaigns.show', $campaign));

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'title' => 'Updated Toronto campaign',
            'country' => 'Canada',
            'country_code' => 'CA',
            'city' => 'Toronto',
            'business_category' => 'Dentist',
            'radius_meters' => 20000,
            'only_without_website' => false,
            'only_with_phone' => false,
        ]);
    }

    public function test_active_campaign_cannot_be_edited(): void
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
            'title' => 'Running campaign',
            'city' => 'Lahore',
            'business_category' => 'Restaurant',
            'status' => 'running',
        ]);

        $this->actingAs($user)
            ->get(route('campaigns.edit', $campaign))
            ->assertStatus(422);
    }
}
