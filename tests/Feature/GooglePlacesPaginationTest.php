<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Service;
use App\Models\User;
use App\Services\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GooglePlacesPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_search_follows_next_page_tokens(): void
    {
        config(['services.google_places.key' => 'test-key']);

        $firstPage = collect(range(1, 20))->map(fn (int $id) => [
            'id' => "place-$id",
            'displayName' => ['text' => "Business $id"],
        ])->all();
        $secondPage = collect(range(21, 40))->map(fn (int $id) => [
            'id' => "place-$id",
            'displayName' => ['text' => "Business $id"],
        ])->all();

        Http::fakeSequence()
            ->push(['places' => $firstPage, 'nextPageToken' => 'page-two'])
            ->push(['places' => $secondPage]);

        $results = app(GooglePlacesService::class)
            ->searchBusinesses('restaurants', 'Lahore', 'Restaurant', 20, null);

        $this->assertCount(40, $results);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => data_get($request->data(), 'pageToken') === 'page-two');
    }

    public function test_campaign_search_uses_multiple_queries_and_deduplicates_places(): void
    {
        config([
            'services.google_places.key' => 'test-key',
            'lead-generator.google_places_max_queries' => 2,
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
            'keyword' => 'family restaurants',
            'required_leads' => 30,
        ]);
        $firstQuery = collect(range(1, 20))->map(fn (int $id) => ['id' => "place-$id"])->all();
        $secondQuery = collect(range(20, 39))->map(fn (int $id) => ['id' => "place-$id"])->all();
        $progress = [];

        Http::fakeSequence()
            ->push(['places' => $firstQuery])
            ->push(['places' => $secondQuery]);

        $results = app(GooglePlacesService::class)->searchCampaignBusinesses(
            $campaign,
            function (int $found, int $queryNumber) use (&$progress): void {
                $progress[] = [$found, $queryNumber];
            }
        );

        $this->assertCount(39, $results);
        $this->assertSame([[20, 1], [39, 2]], $progress);
        Http::assertSentCount(2);
    }

    public function test_campaign_search_uses_selected_country_and_map_radius(): void
    {
        config([
            'services.google_places.key' => 'test-key',
            'lead-generator.google_places_max_queries' => 1,
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
            'title' => 'Toronto restaurants',
            'country' => 'Canada',
            'country_code' => 'CA',
            'province' => 'Ontario',
            'city' => 'Toronto',
            'latitude' => 43.6532,
            'longitude' => -79.3832,
            'radius_meters' => 15000,
            'business_category' => 'Restaurant',
            'required_leads' => 20,
        ]);

        Http::fake(['*' => Http::response(['places' => []])]);

        app(GooglePlacesService::class)->searchCampaignBusinesses($campaign);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['regionCode'] === 'CA'
                && $payload['textQuery'] === 'Restaurant'
                && data_get($payload, 'locationBias.circle.center.latitude') === 43.6532
                && data_get($payload, 'locationBias.circle.center.longitude') === -79.3832
                && data_get($payload, 'locationBias.circle.radius') === 15000.0;
        });

        $service = app(GooglePlacesService::class);
        $matchingLead = [
            'latitude' => 43.6600,
            'longitude' => -79.3900,
            'has_website' => false,
            'phone' => '14165550100',
            'rating' => 4.0,
            'total_reviews' => 20,
        ];
        $outsideLead = array_merge($matchingLead, [
            'latitude' => 44.1000,
            'longitude' => -79.3900,
        ]);

        $this->assertTrue($service->applyFilters($matchingLead, $campaign));
        $this->assertFalse($service->applyFilters($outsideLead, $campaign));
    }
    public function test_social_profile_from_google_is_not_classified_as_a_website(): void
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
            'title' => 'Travel agencies',
            'city' => 'Rawalpindi',
            'business_category' => 'Travel Agency',
        ]);

        $lead = app(GooglePlacesService::class)->normalizeGoogleLead([
            'id' => 'caravan-place',
            'displayName' => ['text' => 'Caravan Travelers'],
            'websiteUri' => 'https://www.instagram.com/caravan.travelers',
        ], $campaign);

        $this->assertFalse($lead['has_website']);
        $this->assertNull($lead['website']);
        $this->assertSame('Social Profile Only', $lead['website_status']);
        $this->assertSame('https://www.instagram.com/caravan.travelers', $lead['instagram_url']);
        $this->assertSame('New Website Opportunity', $lead['opportunity_type']);
    }

    public function test_real_business_website_keeps_clickable_url_data(): void
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
            'title' => 'Travel agencies',
            'city' => 'Rawalpindi',
            'business_category' => 'Travel Agency',
        ]);

        $lead = app(GooglePlacesService::class)->normalizeGoogleLead([
            'id' => 'real-site-place',
            'displayName' => ['text' => 'Example Travels'],
            'websiteUri' => 'https://example-travels.test',
        ], $campaign);

        $this->assertTrue($lead['has_website']);
        $this->assertSame('https://example-travels.test', $lead['website']);
        $this->assertSame('Has Website', $lead['website_status']);
    }
}
