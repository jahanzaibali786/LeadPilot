<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AiCredentialService;
use App\Services\GooglePlacesCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function index(GooglePlacesCredentialService $googleCredentials, AiCredentialService $aiCredentials)
    {
        $userId = auth()->id();
        $secretKeys = array_column(AiCredentialService::PROVIDERS, 'setting');
        $secretKeys[] = GooglePlacesCredentialService::SETTING_KEY;

        return view('settings.index', [
            'settings' => Setting::where('user_id', $userId)
                ->whereNotIn('key', $secretKeys)
                ->pluck('value', 'key'),
            'googlePlacesSource' => $googleCredentials->sourceForUser($userId),
            'googlePlacesMaskedKey' => $googleCredentials->maskedForUser($userId),
            'hasSavedGooglePlacesKey' => Setting::where('user_id', $userId)
                ->where('key', GooglePlacesCredentialService::SETTING_KEY)
                ->exists(),
            'aiProviders' => $aiCredentials->statusForUser($userId),
            'selectedAiProvider' => $aiCredentials->providerForUser($userId),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'default_city' => 'nullable|string|max:150',
            'default_minimum_rating' => 'nullable|numeric|min:0|max:5',
            'default_minimum_reviews' => 'nullable|integer|min:0',
            'maximum_leads_per_campaign' => 'nullable|integer|min:1|max:500',
            'ai_scoring' => 'nullable|boolean',
            'auto_pitch_generation' => 'nullable|boolean',
            'google_places_api_key' => 'nullable|string|min:20|max:255',
            'remove_google_places_api_key' => 'nullable|boolean',
            'ai_provider' => ['nullable', Rule::in(array_keys(AiCredentialService::PROVIDERS))],
        ];
        foreach (AiCredentialService::PROVIDERS as $provider => $definition) {
            $rules[$definition['setting']] = 'nullable|string|min:10|max:512';
            $rules['remove_'.$definition['setting']] = 'nullable|boolean';
            $rules['ai_model_'.$provider] = 'nullable|string|max:150';
        }
        $data = $request->validate($rules);

        $userId = auth()->id();
        foreach (['default_city', 'default_minimum_rating', 'default_minimum_reviews', 'maximum_leads_per_campaign', 'ai_provider'] as $key) {
            if (array_key_exists($key, $data)) {
                Setting::updateOrCreate(['user_id' => $userId, 'key' => $key], ['value' => $data[$key]]);
            }
        }

        foreach (['ai_scoring', 'auto_pitch_generation'] as $key) {
            Setting::updateOrCreate(
                ['user_id' => $userId, 'key' => $key],
                ['value' => $request->boolean($key) ? '1' : '0']
            );
        }

        if ($request->boolean('remove_google_places_api_key')) {
            Setting::where('user_id', $userId)->where('key', GooglePlacesCredentialService::SETTING_KEY)->delete();
        } elseif (filled($data['google_places_api_key'] ?? null)) {
            $this->saveEncrypted($userId, GooglePlacesCredentialService::SETTING_KEY, $data['google_places_api_key']);
        }

        foreach (AiCredentialService::PROVIDERS as $provider => $definition) {
            $modelKey = 'ai_model_'.$provider;
            if (filled($data[$modelKey] ?? null)) {
                Setting::updateOrCreate(['user_id' => $userId, 'key' => $modelKey], ['value' => trim($data[$modelKey])]);
            }

            if ($request->boolean('remove_'.$definition['setting'])) {
                Setting::where('user_id', $userId)->where('key', $definition['setting'])->delete();
            } elseif (filled($data[$definition['setting']] ?? null)) {
                $this->saveEncrypted($userId, $definition['setting'], $data[$definition['setting']]);
            }
        }

        return back()->with('success', 'Settings saved. New AI requests will use the selected provider and model.');
    }

    private function saveEncrypted(int $userId, string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => Crypt::encryptString(trim($value)), 'is_encrypted' => true]
        );
    }
}
