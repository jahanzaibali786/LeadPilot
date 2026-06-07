<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class AiCredentialService
{
    public const PROVIDERS = [
        'openrouter' => ['label' => 'OpenRouter', 'setting' => 'openrouter_api_key', 'config' => 'services.openrouter.key', 'model' => 'openrouter/free'],
        'gemini' => ['label' => 'Google Gemini', 'setting' => 'gemini_api_key', 'config' => 'services.gemini.key', 'model' => 'gemini-2.5-flash'],
        'xai' => ['label' => 'xAI / Grok', 'setting' => 'xai_api_key', 'config' => 'services.xai.key', 'model' => 'grok-4.3'],
        'anthropic' => ['label' => 'Claude / Anthropic', 'setting' => 'anthropic_api_key', 'config' => 'services.anthropic.key', 'model' => 'claude-sonnet-4-20250514'],
    ];

    public function providerForUser(?int $userId): string
    {
        $provider = Setting::valueFor($userId, 'ai_provider', 'openrouter');
        return array_key_exists($provider, self::PROVIDERS) ? $provider : 'openrouter';
    }

    public function keyForUser(?int $userId, ?string $provider = null): ?string
    {
        $provider ??= $this->providerForUser($userId);
        $definition = self::PROVIDERS[$provider] ?? null;
        if (! $definition) return null;

        if ($userId) {
            $setting = Setting::where('user_id', $userId)->where('key', $definition['setting'])->first();
            if ($setting?->value) {
                try {
                    return $setting->is_encrypted ? Crypt::decryptString($setting->value) : $setting->value;
                } catch (Throwable) {
                    // Fall through to the environment key when stored ciphertext is invalid.
                }
            }
        }

        return config($definition['config']) ?: null;
    }

    public function modelForUser(?int $userId, ?string $provider = null): string
    {
        $provider ??= $this->providerForUser($userId);
        $definition = self::PROVIDERS[$provider] ?? self::PROVIDERS['openrouter'];
        return Setting::valueFor($userId, 'ai_model_'.$provider, config('services.'.$provider.'.model', $definition['model']));
    }

    public function statusForUser(?int $userId): array
    {
        $statuses = [];
        foreach (self::PROVIDERS as $provider => $definition) {
            $key = $this->keyForUser($userId, $provider);
            $saved = $userId && Setting::where('user_id', $userId)->where('key', $definition['setting'])->exists();
            $statuses[$provider] = [
                'label' => $definition['label'],
                'configured' => filled($key),
                'saved' => $saved,
                'source' => $saved ? 'Saved in Settings' : (filled(config($definition['config'])) ? 'Environment (.env)' : 'Not configured'),
                'masked' => $this->mask($key),
                'model' => $this->modelForUser($userId, $provider),
            ];
        }
        return $statuses;
    }

    private function mask(?string $key): ?string
    {
        if (! $key) return null;
        if (strlen($key) <= 10) return str_repeat('*', strlen($key));
        return substr($key, 0, 5).str_repeat('*', max(5, strlen($key) - 10)).substr($key, -5);
    }
}