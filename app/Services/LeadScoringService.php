<?php

namespace App\Services;

use App\Models\Campaign;

class LeadScoringService
{
    public function score(array $lead, ?Campaign $campaign = null): array
    {
        $score = 0;
        $reasons = [];
        $add = function (int $points, string $reason) use (&$score, &$reasons) { $score += $points; $reasons[] = $reason; };

        empty($lead['website']) ? $add(35, 'No website listed') : $add(-30, 'Website already listed');
        ! empty($lead['phone']) ? $add(20, 'Phone available') : $add(-20, 'No phone available');
        if (! empty($lead['whatsapp_number'])) $add(10, 'WhatsApp-ready number');
        if (($lead['rating'] ?? 0) >= 4) $add(10, 'Strong customer rating');
        elseif (($lead['rating'] ?? 5) < 3) $add(-5, 'Low rating');
        if (($lead['total_reviews'] ?? 0) >= 20) $add(10, 'Established local visibility');
        if (! empty($lead['address'])) $add(5, 'Complete address');
        else $add(-10, 'Missing address');
        if ($campaign && str_contains(strtolower($lead['business_category'] ?? ''), strtolower($campaign->business_category))) $add(10, 'Category matches campaign');
        if ($campaign && strcasecmp($lead['city'] ?? '', $campaign->city) === 0) $add(10, 'City matches campaign');

        $score = max(0, min(100, $score));
        $quality = $score >= 80 ? 'Hot' : ($score >= 50 ? 'Warm' : 'Cold');
        return ['lead_score' => $score, 'lead_quality' => $quality, 'match_reason' => implode('. ', $reasons).'.'];
    }
}
