<?php

namespace App\Services;

class PhoneNormalizerService
{
    public function normalize(?string $phone): array
    {
        $original = $phone;
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($digits, '0092')) $digits = substr($digits, 2);
        if (str_starts_with($digits, '0')) $digits = '92'.substr($digits, 1);
        if (strlen($digits) === 10 && str_starts_with($digits, '3')) $digits = '92'.$digits;
        $valid = preg_match('/^92\d{10}$/', $digits) === 1;

        return [
            'original_phone' => $original,
            'phone' => $valid ? $digits : ($digits ?: null),
            'formatted_phone' => $valid ? '+'.$digits : $original,
            'whatsapp_number' => $valid ? '+'.$digits : null,
        ];
    }
}
