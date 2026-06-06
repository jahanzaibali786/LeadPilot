<?php

namespace App\Services;

use App\Models\Lead;

class DuplicateLeadService
{
    public function find(int $userId, array $data): ?Lead
    {
        return Lead::where('user_id', $userId)->where(function ($query) use ($data) {
            if (! empty($data['google_place_id'])) $query->orWhere('google_place_id', $data['google_place_id']);
            if (! empty($data['phone'])) $query->orWhere('phone', $data['phone']);
            if (! empty($data['website'])) $query->orWhere('website', $data['website']);
            if (! empty($data['business_name']) && ! empty($data['city'])) {
                $query->orWhere(fn ($q) => $q->where('business_name', $data['business_name'])->where('city', $data['city']));
            }
        })->first();
    }
}
