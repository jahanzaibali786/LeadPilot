<?php

namespace Tests\Feature;

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
}
