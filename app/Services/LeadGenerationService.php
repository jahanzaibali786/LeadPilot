<?php

namespace App\Services;

use App\Models\Blacklist;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeadGenerationService
{
    public function __construct(
        private GooglePlacesService $places,
        private DuplicateLeadService $duplicates,
        private LeadScoringService $scoring,
    ) {}

    public function run(Campaign $campaign): void
    {
        $campaign->update(['status'=>'running','progress_percentage'=>5,'failure_reason'=>null]);
        Log::channel('campaigns')->info('Campaign generation entered running state.', [
            'campaign_id' => $campaign->id,
            'keyword' => $campaign->keyword ?: $campaign->business_category,
            'city' => $campaign->city,
        ]);

        try {
            $places = $this->places->searchCampaignBusinesses(
                $campaign,
                function (int $found, int $queryNumber, int $queryTotal) use ($campaign): void {
                    $campaign->update([
                        'total_found' => $found,
                        'progress_percentage' => min(24, 5 + (int) floor(19 * ($queryNumber / max($queryTotal, 1)))),
                    ]);
                }
            );
            $campaign->update(['total_found'=>count($places),'progress_percentage'=>25]);
            Log::channel('campaigns')->info('Google Places search completed.', [
                'campaign_id' => $campaign->id,
                'places_found' => count($places),
            ]);

            $seen = [];
            $savedCount = $campaign->leads()->count();

            foreach ($places as $index => $place) {
                if ($campaign->fresh()->status === 'cancelled') return;
                $campaign->update([
                    'progress_percentage' => min(90, 25 + (int) (65 * (($index + 1) / max(count($places), 1)))),
                ]);
                $data = $this->places->normalizeGoogleLead($place, $campaign);
                if (! $this->places->applyFilters($data, $campaign) || $this->blacklisted($campaign->user_id, $data)) continue;

                if (! empty($data['website'])) {
                    $socialLinks = array_filter(
                        app(SocialMediaDiscoveryService::class)->discover($data['website']),
                        fn ($url) => filled($url)
                    );
                    $data = array_merge($data, $socialLinks);
                }

                $identity = $this->duplicates->identityKey($data);
                if (isset($seen[$identity])) {
                    $campaign->increment('duplicates_removed');
                    continue;
                }
                $seen[$identity] = true;

                $scoredData = array_merge($data, $this->scoring->score($data, $campaign), [
                    'suggested_offer'=>$this->offer($data['business_category']),
                ]);

                $existing = $this->duplicates->find($campaign->user_id, $data);
                if ($existing) {
                    if ($existing->campaign_id === $campaign->id) {
                        $existing->update($scoredData);
                        $this->syncCampaignLeadTotals($campaign);
                    } else {
                        $campaign->increment('duplicates_removed');
                    }
                    continue;
                }

                $lead = Lead::create($scoredData + [
                    'user_id'=>$campaign->user_id,
                    'campaign_id'=>$campaign->id,
                    'service_id'=>$campaign->service_id,
                ]);
                $lead->activities()->create(['user_id'=>$campaign->user_id,'action'=>'created','description'=>'Lead imported from Google Places API.']);
                $savedCount++;
                $this->syncCampaignLeadTotals($campaign);

                if ($savedCount >= $campaign->required_leads) break;
            }

            $this->syncCampaignLeadTotals($campaign);
            $campaign->update(['status'=>'completed','progress_percentage'=>100,'completed_at'=>now()]);
            Log::channel('campaigns')->info('Campaign completed.', [
                'campaign_id' => $campaign->id,
                'total_found' => $campaign->fresh()->total_found,
                'total_saved' => $campaign->fresh()->total_saved,
            ]);
        } catch (Throwable $e) {
            report($e);
            Log::channel('campaigns')->error('Campaign generation failed.', [
                'campaign_id' => $campaign->id,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            $campaign->increment('failed_requests');
            $campaign->update(['status'=>'failed','failure_reason'=>$e->getMessage(),'completed_at'=>now()]);
        }
    }

    private function syncCampaignLeadTotals(Campaign $campaign): void
    {
        $qualityCounts = $campaign->leads()
            ->selectRaw('lead_quality, count(*) total')
            ->groupBy('lead_quality')
            ->pluck('total', 'lead_quality');
        $total = $qualityCounts->sum();

        $campaign->update([
            'total_saved' => $total,
            'valid_leads' => $total,
            'hot_leads' => (int) ($qualityCounts['Hot'] ?? 0),
            'warm_leads' => (int) ($qualityCounts['Warm'] ?? 0),
            'cold_leads' => (int) ($qualityCounts['Cold'] ?? 0),
        ]);
    }

    private function blacklisted(int $userId, array $data): bool
    {
        return Blacklist::where('user_id',$userId)->get()->contains(fn($item) => match($item->type) {
            'phone' => $data['phone'] === $item->value, 'business_name' => stripos($data['business_name'],$item->value)!==false,
            'city' => strcasecmp($data['city'],$item->value)===0, 'category' => stripos($data['business_category'],$item->value)!==false,
            'domain' => $data['website'] && str_contains($data['website'],$item->value), default => false,
        });
    }

    private function offer(string $category): string
    {
        $category = strtolower($category);
        return match(true) {
            str_contains($category,'restaurant') => 'Menu, gallery, WhatsApp ordering and Maps website',
            str_contains($category,'school') => 'Admissions, gallery, results and inquiry website',
            str_contains($category,'clinic') || str_contains($category,'dentist') => 'Doctor profile, services and appointment website',
            str_contains($category,'real estate') => 'Property listing and inquiry website',
            str_contains($category,'hotel') => 'Rooms, gallery and booking inquiry website',
            default => 'Professional business website with services, gallery, WhatsApp and Maps',
        };
    }
}
