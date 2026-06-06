<?php

namespace App\Services;

class PhoneNormalizerService
{
    public function normalize(?string $phone, ?string $countryCode = null): array
    {
        $original = $phone;
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $isInternational = str_starts_with(trim((string) $phone), '+') || str_starts_with($digits, '00');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strtoupper((string) $countryCode) === 'PK' && ! $isInternational) {
            if (str_starts_with($digits, '0')) $digits = '92'.substr($digits, 1);
            if (strlen($digits) === 10 && str_starts_with($digits, '3')) $digits = '92'.$digits;
        }

        $valid = preg_match('/^[1-9]\d{7,14}$/', $digits) === 1
            && ($isInternational || strtoupper((string) $countryCode) === 'PK');

        return [
            'original_phone' => $original,
            'phone' => $valid ? $digits : ($digits ?: null),
            'formatted_phone' => $valid ? '+'.$digits : $original,
            'whatsapp_number' => $valid ? '+'.$digits : null,
        ];
    }
}
