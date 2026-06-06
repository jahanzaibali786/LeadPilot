<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Services\DuplicateLeadService;
use App\Services\GooglePlacesService;
use App\Services\LeadGenerationService;
use App\Services\LeadScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CampaignLeadPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rerun_refreshes_existing_campaign_lead_without_losing_saved_count(): void
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
            'title' => 'Restaurants in Lahore',
            'city' => 'Lahore',
            'business_category' => 'Restaurant',
            'required_leads' => 20,
        ]);
        $lead = Lead::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'campaign_id' => $campaign->id,
            'google_place_id' => 'place-1',
            'business_name' => 'Existing Restaurant',
            'business_category' => 'Restaurant',
            'city' => 'Lahore',
            'phone' => '923001234567',
            'rating' => 3.5,
            'lead_score' => 70,
            'lead_quality' => 'Warm',
        ]);

        $normalized = [
            'google_place_id' => 'place-1',
            'business_name' => 'Existing Restaurant',
            'business_category' => 'Restaurant',
            'city' => 'Lahore',
            'province' => 'Punjab',
            'country' => 'Pakistan',
            'phone' => '923001234567',
            'formatted_phone' => '+923001234567',
            'whatsapp_number' => '+923001234567',
            'website' => null,
            'has_website' => false,
            'website_status' => 'No Website',
            'rating' => 4.6,
            'total_reviews' => 120,
            'address' => 'Main Boulevard, Lahore',
            'source_name' => 'Google Places API',
        ];

        $places = Mockery::mock(GooglePlacesService::class);
        $places->shouldReceive('searchCampaignBusinesses')->once()->andReturn([['id' => 'place-1']]);
        $places->shouldReceive('normalizeGoogleLead')->once()->andReturn($normalized);
        $places->shouldReceive('applyFilters')->once()->andReturnTrue();

        (new LeadGenerationService(
            $places,
            new DuplicateLeadService(),
            new LeadScoringService()
        ))->run($campaign);

        $this->assertSame(1, Lead::where('campaign_id', $campaign->id)->count());
        $this->assertSame(4.6, (float) $lead->fresh()->rating);
        $this->assertSame(1, $campaign->fresh()->total_saved);
        $this->assertSame(0, $campaign->fresh()->duplicates_removed);
    }
}
