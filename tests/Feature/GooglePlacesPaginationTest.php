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
}
