<?php

namespace App\Services;

use App\Models\Blacklist;
use App\Models\Campaign;
use App\Models\Lead;
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
        $campaign->update(['status'=>'running','progress_percentage'=>5,'started_at'=>now(),'failure_reason'=>null]);
        try {
            $places = $this->places->searchBusinesses($campaign->keyword ?: $campaign->business_category, $campaign->city, $campaign->business_category);
            $campaign->update(['total_found'=>count($places),'progress_percentage'=>25]);
            foreach ($places as $index => $place) {
                if ($campaign->fresh()->status === 'cancelled') return;
                $campaign->update([
                    'progress_percentage' => min(90, 25 + (int) (65 * (($index + 1) / max(count($places), 1)))),
                ]);
                $data = $this->places->normalizeGoogleLead($place, $campaign);
                if (! $this->places->applyFilters($data, $campaign) || $this->blacklisted($campaign->user_id, $data)) continue;
                if ($this->duplicates->find($campaign->user_id, $data)) {
                    $campaign->increment('duplicates_removed');
                    continue;
                }
                $data = array_merge($data, $this->scoring->score($data, $campaign), [
                    'user_id'=>$campaign->user_id,'campaign_id'=>$campaign->id,'service_id'=>$campaign->service_id,
                    'suggested_offer'=>$this->offer($data['business_category']),
                ]);
                $lead = Lead::create($data);
                $lead->activities()->create(['user_id'=>$campaign->user_id,'action'=>'created','description'=>'Lead imported from Google Places API.']);
                $campaign->increment('total_saved');
                $campaign->increment('valid_leads');
                $campaign->increment(strtolower($lead->lead_quality).'_leads');
                if ($campaign->total_saved >= $campaign->required_leads) break;
            }
            $campaign->update(['status'=>'completed','progress_percentage'=>100,'completed_at'=>now()]);
        } catch (Throwable $e) {
            report($e);
            $campaign->increment('failed_requests');
            $campaign->update(['status'=>'failed','failure_reason'=>$e->getMessage(),'completed_at'=>now()]);
        }
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
