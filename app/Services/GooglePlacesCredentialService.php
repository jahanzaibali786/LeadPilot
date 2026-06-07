<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class GooglePlacesCredentialService
{
    public const SETTING_KEY = 'google_places_api_key';

    public function forUser(?int $userId): ?string
    {
        if ($userId) {
            $setting = Setting::where('user_id', $userId)
                ->where('key', self::SETTING_KEY)
                ->first();

            if ($setting?->value) {
                try {
                    return $setting->is_encrypted
                        ? Crypt::decryptString($setting->value)
                        : $setting->value;
                } catch (Throwable) {
                    // Fall back to the environment key if stored ciphertext is invalid.
                }
            }
        }

        return config('services.google_places.key') ?: null;
    }

    public function sourceForUser(?int $userId): string
    {
        $hasSavedKey = $userId && Setting::where('user_id', $userId)
            ->where('key', self::SETTING_KEY)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();

        if ($hasSavedKey) {
            return 'Saved in Settings';
        }

        return config('services.google_places.key') ? 'Environment (.env)' : 'Not configured';
    }

    public function maskedForUser(?int $userId): ?string
    {
        $key = $this->forUser($userId);
        if (! $key) {
            return null;
        }

        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 4).str_repeat('*', max(4, strlen($key) - 8)).substr($key, -4);
    }
}