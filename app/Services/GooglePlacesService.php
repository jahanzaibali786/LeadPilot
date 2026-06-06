<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Models\Campaign;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GooglePlacesService
{
    private const BASE = 'https://places.googleapis.com/v1';

    public function searchBusinesses(
        string $keyword,
        string $city,
        string $category,
        int $requiredResults = 20,
        ?int $campaignId = null,
        ?string $countryCode = null,
        ?string $country = null,
        ?string $province = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $radiusMeters = null
    ): array
    {
        $limit = min(60, max(20, $requiredResults * 2));
        $searchTerm = trim($keyword) ?: trim($category);
        $location = implode(', ', array_filter([$city, $province, $country]));
        $payload = [
            'textQuery' => $latitude !== null && $longitude !== null
                ? $searchTerm
                : trim("$searchTerm in $location"),
            'languageCode' => 'en',
            'pageSize' => 20,
        ];
        if ($countryCode) {
            $payload['regionCode'] = strtoupper($countryCode);
        }
        if ($latitude !== null && $longitude !== null) {
            $payload['locationBias'] = [
                'circle' => [
                    'center' => ['latitude' => $latitude, 'longitude' => $longitude],
                    'radius' => (float) min(50000, max(500, $radiusMeters ?? 10000)),
                ],
            ];
        }
        $fields = 'places.id,places.displayName,places.formattedAddress,places.location,places.rating,places.userRatingCount,places.types,places.websiteUri,places.nationalPhoneNumber,places.internationalPhoneNumber,places.googleMapsUri,nextPageToken';
        $places = [];
        $pageToken = null;
        $page = 0;

        do {
            $page++;
            $requestPayload = $payload;
            if ($pageToken) {
                $requestPayload['pageToken'] = $pageToken;
            }

            $response = $this->request(
                '/places:searchText',
                $requestPayload,
                $fields,
                $campaignId,
                $page
            );

            foreach ($response->json('places', []) as $place) {
                $key = $place['id'] ?? md5(json_encode($place));
                $places[$key] = $place;
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken && count($places) < $limit && $page < 3);

        return array_slice(array_values($places), 0, $limit);
    }

    public function searchCampaignBusinesses(Campaign $campaign, ?callable $onProgress = null): array
    {
        $maxQueries = max(1, config('lead-generator.google_places_max_queries', 10));
        $existingLeads = $campaign->leads()->count();
        $remainingTarget = max(1, $campaign->required_leads - $existingLeads);
        $candidateTarget = min($maxQueries * 60, max(60, $remainingTarget * 2));
        $terms = array_slice($this->campaignSearchTerms($campaign), 0, $maxQueries);
        $places = [];

        foreach ($terms as $index => $term) {
            $results = $this->searchBusinesses(
                $term,
                $campaign->city,
                $campaign->business_category,
                30,
                $campaign->id,
                $campaign->country_code,
                $campaign->country,
                $campaign->province,
                $campaign->latitude,
                $campaign->longitude,
                $campaign->radius_meters
            );

            foreach ($results as $place) {
                $key = $place['id'] ?? md5(json_encode($place));
                $places[$key] = $place;
            }

            if ($onProgress) {
                $onProgress(count($places), $index + 1, count($terms));
            }

            if (count($places) >= $candidateTarget) {
                break;
            }
        }

        return array_slice(array_values($places), 0, $candidateTarget);
    }

    public function getPlaceDetails(string $placeId): array
    {
        $response = Http::withHeaders([
            'X-Goog-Api-Key' => config('services.google_places.key'),
            'X-Goog-FieldMask' => 'id,displayName,formattedAddress,location,rating,userRatingCount,types,websiteUri,nationalPhoneNumber,internationalPhoneNumber,googleMapsUri',
        ])->timeout(20)->get(self::BASE.'/places/'.$placeId);
        if ($response->failed()) throw new RuntimeException($this->message($response));
        return $response->json();
    }

    public function normalizeGoogleLead(array $place, Campaign $campaign): array
    {
        $phone = app(PhoneNormalizerService::class)->normalize(
            $place['internationalPhoneNumber'] ?? $place['nationalPhoneNumber'] ?? null,
            $campaign->country_code
        );
        $website = $place['websiteUri'] ?? null;
        return array_merge($phone, [
            'google_place_id' => $place['id'] ?? null,
            'google_maps_url' => $place['googleMapsUri'] ?? $this->generateGoogleMapsUrl($place['id'] ?? null, data_get($place, 'location.latitude'), data_get($place, 'location.longitude')),
            'business_name' => data_get($place, 'displayName.text', 'Unknown business'),
            'business_category' => str_replace('_', ' ', $place['types'][0] ?? $campaign->business_category),
            'address' => $place['formattedAddress'] ?? null, 'city' => $campaign->city, 'province' => $campaign->province,
            'country' => $campaign->country, 'latitude' => data_get($place, 'location.latitude'), 'longitude' => data_get($place, 'location.longitude'),
            'rating' => $place['rating'] ?? null, 'total_reviews' => $place['userRatingCount'] ?? 0,
            'website' => $website, 'has_website' => ! empty($website),
            'website_status' => $website ? 'Has Website' : 'No Website',
            'online_presence_status' => $website ? 'Has Website' : (! empty($phone['phone']) ? 'Google Listing + Phone' : 'Google Listing Only'),
            'opportunity_type' => $website ? 'Website Redesign Opportunity' : 'New Website Opportunity',
            'source_name' => 'Google Places API', 'source_url' => $place['googleMapsUri'] ?? null, 'last_checked_at' => now(),
        ]);
    }

    public function hasWebsite(array $place): bool { return ! empty($place['websiteUri']); }
    public function generateGoogleMapsUrl(?string $placeId, mixed $lat = null, mixed $lng = null): ?string
    {
        if ($placeId) return 'https://www.google.com/maps/place/?q=place_id:'.$placeId;
        return $lat && $lng ? "https://www.google.com/maps?q=$lat,$lng" : null;
    }

    public function applyFilters(array $lead, Campaign $campaign): bool
    {
        if (! $this->insideCampaignRadius($lead, $campaign)) return false;
        if ($campaign->only_without_website && $lead['has_website']) return false;
        if ($campaign->only_with_phone && empty($lead['phone'])) return false;
        if (($lead['rating'] ?? 0) < $campaign->minimum_rating) return false;
        if (($lead['total_reviews'] ?? 0) < $campaign->minimum_reviews) return false;
        return true;
    }

    private function insideCampaignRadius(array $lead, Campaign $campaign): bool
    {
        if (
            $campaign->latitude === null
            || $campaign->longitude === null
            || ! isset($lead['latitude'], $lead['longitude'])
        ) {
            return true;
        }

        $earthRadius = 6371000;
        $lat1 = deg2rad((float) $campaign->latitude);
        $lat2 = deg2rad((float) $lead['latitude']);
        $latDelta = $lat2 - $lat1;
        $lngDelta = deg2rad((float) $lead['longitude'] - (float) $campaign->longitude);
        $a = sin($latDelta / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($lngDelta / 2) ** 2;
        $distance = $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $distance <= ($campaign->radius_meters ?? 10000);
    }

    private function request(
        string $endpoint,
        array $payload,
        string $fields,
        ?int $campaignId = null,
        int $page = 1
    ): Response
    {
        $started = microtime(true);
        $response = Http::withHeaders(['X-Goog-Api-Key' => config('services.google_places.key'), 'X-Goog-FieldMask' => $fields])
            ->timeout(30)->post(self::BASE.$endpoint, $payload);
        ApiLog::create([
            'campaign_id' => $campaignId,
            'provider' => 'google_places',
            'endpoint' => $endpoint,
            'status_code' => $response->status(),
            'request_data' => $payload,
            'response_meta' => [
                'page' => $page,
                'count' => count($response->json('places', [])),
                'has_next_page' => (bool) $response->json('nextPageToken'),
            ],
            'error' => $response->failed() ? $this->message($response) : null,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
        if ($response->failed()) throw new RuntimeException($this->message($response));
        return $response;
    }

    private function campaignSearchTerms(Campaign $campaign): array
    {
        $category = trim($campaign->business_category);
        $service = $campaign->relationLoaded('service')
            ? $campaign->service
            : $campaign->service()->first();
        $relatedCategories = data_get($service, 'ai_analysis.related_business_categories', []);
        $baseTerms = [
            $campaign->keyword,
            $category,
            Str::singular($category),
            Str::plural($category),
            ...array_slice(is_array($relatedCategories) ? $relatedCategories : [], 0, 3),
        ];
        $terms = [];

        foreach ($baseTerms as $term) {
            $term = trim((string) $term);
            if ($term !== '') {
                $terms[] = $term;
            }
        }

        foreach (['best', 'top rated', 'popular', 'local', 'nearby', 'recommended', 'established', 'near city center'] as $modifier) {
            $terms[] = $modifier === 'near city center'
                ? "$category $modifier"
                : "$modifier $category";
        }

        return array_values(array_unique(array_filter($terms), SORT_STRING));
    }

    private function message(Response $response): string { return $response->json('error.message') ?? 'Google Places API request failed.'; }
}
