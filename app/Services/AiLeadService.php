<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\Lead;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;
use Throwable;

class AiLeadService
{
    private const OUTPUT_FIELDS = [
        'lead_score', 'lead_quality', 'opportunity_type', 'match_reason', 'suggested_offer',
        'suggested_pitch', 'whatsapp_message_english', 'whatsapp_message_roman_urdu',
        'email_subject', 'email_body', 'call_script',
    ];

    private const OPENROUTER_FALLBACK_MODELS = [
        'nvidia/nemotron-nano-9b-v2:free',
        'poolside/laguna-xs.2-20260421:free',
        'openrouter/free',
    ];

    public function __construct(private AiCredentialService $credentials) {}

    public function analyzeLead(Lead $lead): array
    {
        $selectedProvider = $this->credentials->providerForUser($lead->user_id);
        $errors = [];

        foreach ($this->providerOrder($lead->user_id, $selectedProvider) as $provider) {
            $key = $this->credentials->keyForUser($lead->user_id, $provider);
            if (! $key) {
                continue;
            }

            foreach ($this->modelsForProvider($lead->user_id, $provider) as $model) {
                try {
                    [$response, $text, $inputTokens, $outputTokens, $usedModel] = match ($provider) {
                        'gemini' => $this->gemini($key, $model, $lead),
                        'xai' => $this->openAiCompatible('https://api.x.ai/v1/chat/completions', $key, $model, $lead, true),
                        'anthropic' => $this->anthropic($key, $model, $lead),
                        default => $this->openAiCompatible('https://openrouter.ai/api/v1/chat/completions', $key, $model, $lead, false),
                    };

                    if ($response->failed()) {
                        throw new RuntimeException($this->errorMessage($response, $provider));
                    }

                    $data = $this->parse($text, $lead);
                    $this->log($lead, $provider, $usedModel ?: $model, true, null, $inputTokens, $outputTokens);

                    return $data;
                } catch (Throwable $exception) {
                    $errors[] = AiCredentialService::PROVIDERS[$provider]['label'].' ('.$model.'): '.$exception->getMessage();
                    $this->log($lead, $provider, $model, false, $exception->getMessage());
                }
            }
        }

        if ($errors === []) {
            throw new RuntimeException('No configured AI provider key was found. Add a provider key in Settings.');
        }

        throw new RuntimeException('All configured AI providers failed. Last errors: '.implode(' | ', array_slice($errors, -4)));
    }

    private function providerOrder(?int $userId, string $selectedProvider): array
    {
        $providers = [$selectedProvider, 'openrouter', 'gemini', 'anthropic', 'xai'];

        return array_values(array_filter(array_unique($providers), fn (string $provider) =>
            array_key_exists($provider, AiCredentialService::PROVIDERS)
            && $this->credentials->keyForUser($userId, $provider)
        ));
    }

    private function modelsForProvider(?int $userId, string $provider): array
    {
        $selected = $this->credentials->modelForUser($userId, $provider);
        if ($provider !== 'openrouter') {
            return [$selected];
        }

        return array_values(array_unique([$selected, ...self::OPENROUTER_FALLBACK_MODELS]));
    }

    private function log(
        Lead $lead,
        string $provider,
        ?string $model,
        bool $successful,
        ?string $error = null,
        int $inputTokens = 0,
        int $outputTokens = 0
    ): void {
        AiLog::create([
            'user_id' => $lead->user_id,
            'lead_id' => $lead->id,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'successful' => $successful,
            'error' => $error,
        ]);
    }

    private function openAiCompatible(string $url, string $key, string $model, Lead $lead, bool $strictSchema): array
    {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Return valid JSON only. No markdown fences. Every requested string field must be non-empty.'],
                ['role' => 'user', 'content' => $this->prompt($lead)],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1000,
            'response_format' => $strictSchema ? $this->responseFormat() : ['type' => 'json_object'],
        ];
        if (str_contains($url, 'openrouter.ai')) {
            $payload['plugins'] = [['id' => 'response-healing']];
        }

        $response = Http::withToken($key)
            ->withHeaders(str_contains($url, 'openrouter.ai') ? ['HTTP-Referer' => config('app.url'), 'X-Title' => config('app.name')] : [])
            ->connectTimeout(10)->timeout(150)->post($url, $payload);

        return [
            $response,
            (string) $response->json('choices.0.message.content', ''),
            (int) $response->json('usage.prompt_tokens', 0),
            (int) $response->json('usage.completion_tokens', 0),
            (string) $response->json('model', $model),
        ];
    }

    private function gemini(string $key, string $model, Lead $lead): array
    {
        $response = Http::withHeaders(['x-goog-api-key' => $key])->connectTimeout(10)->timeout(150)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'systemInstruction' => ['parts' => [['text' => 'Return valid JSON matching the requested schema. Every requested string field must be non-empty.']]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $this->prompt($lead)]]]],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 1000,
                    'responseMimeType' => 'application/json',
                    'responseJsonSchema' => $this->schema(),
                ],
            ]);

        return [
            $response,
            (string) $response->json('candidates.0.content.parts.0.text', ''),
            (int) $response->json('usageMetadata.promptTokenCount', 0),
            (int) $response->json('usageMetadata.candidatesTokenCount', 0),
            $model,
        ];
    }

    private function anthropic(string $key, string $model, Lead $lead): array
    {
        $response = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->connectTimeout(10)->timeout(150)->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1000,
                'system' => 'Return valid JSON only. No markdown fences. Every requested string field must be non-empty.',
                'messages' => [['role' => 'user', 'content' => $this->prompt($lead)]],
            ]);

        return [
            $response,
            (string) $response->json('content.0.text', ''),
            (int) $response->json('usage.input_tokens', 0),
            (int) $response->json('usage.output_tokens', 0),
            (string) $response->json('model', $model),
        ];
    }

    private function parse(string $text, Lead $lead): array
    {
        try {
            $data = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', $text)), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The AI provider returned invalid JSON.', previous: $exception);
        }
        if (! is_array($data)) throw new RuntimeException('The AI provider returned an invalid response.');

        $data = array_intersect_key($data, array_flip(self::OUTPUT_FIELDS));
        $fallbacks = $this->fallbacks($lead);
        foreach (self::OUTPUT_FIELDS as $field) {
            if ($field === 'lead_score' || $field === 'lead_quality') {
                continue;
            }
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value !== '' ? $value : $fallbacks[$field];
        }
        $data['lead_score'] = max(0, min(100, (int) ($data['lead_score'] ?? 0)));
        $data['lead_quality'] = in_array($data['lead_quality'] ?? null, ['Hot', 'Warm', 'Cold'], true)
            ? $data['lead_quality']
            : ($data['lead_score'] >= 80 ? 'Hot' : ($data['lead_score'] >= 50 ? 'Warm' : 'Cold'));
        return $data;
    }

    private function fallbacks(Lead $lead): array
    {
        $business = $lead->business_name ?: 'your business';
        $category = $lead->business_category ?: 'business';
        $city = $lead->city ?: 'your area';
        $offer = 'Professional website with services, gallery, Google Maps, and WhatsApp inquiry buttons';

        return [
            'opportunity_type' => $lead->has_website ? 'Website Redesign Opportunity' : 'New Website Opportunity',
            'match_reason' => "{$business} is a {$category} in {$city}, so a clearer web presence can help convert online searches into inquiries.",
            'suggested_offer' => $offer,
            'suggested_pitch' => "Position the website as a simple way for {$business} to turn searches into direct WhatsApp and call inquiries.",
            'whatsapp_message_english' => "Hi {$business}, I noticed your online listing and had a quick idea: a clean website with services, photos, Maps, and WhatsApp buttons could help more customers contact you directly. Would you like me to share a simple plan?",
            'whatsapp_message_roman_urdu' => "Assalam o Alaikum {$business}, aap ke liye aik simple website idea hai jisme services, photos, Maps aur WhatsApp button ho. Is se customers direct contact kar sakte hain. Kya main short plan share karun?",
            'email_subject' => "Website idea for {$business}",
            'email_body' => "Hi {$business},\n\nI came across your {$category} listing in {$city}. A focused website with your services, photos, Google Maps location, and WhatsApp inquiry buttons could make it easier for customers to understand your offer and contact you directly.\n\nI can help create a simple, mobile-friendly website tailored to your business. Would you like me to send a short plan?\n\nBest regards,",
            'call_script' => "Hi, am I speaking with {$business}? I found your {$category} listing and wanted to share a quick website idea. A mobile-friendly site with your services, photos, Maps, and WhatsApp contact can help customers reach you directly. Would this be useful for your business?",
        ];
    }

    private function responseFormat(): array
    {
        return ['type' => 'json_schema', 'json_schema' => ['name' => 'lead_analysis', 'strict' => true, 'schema' => $this->schema()]];
    }

    private function schema(): array
    {
        $properties = [
            'lead_score' => ['type' => 'integer'],
            'lead_quality' => ['type' => 'string', 'enum' => ['Hot', 'Warm', 'Cold']],
        ];
        foreach (array_slice(self::OUTPUT_FIELDS, 2) as $field) $properties[$field] = ['type' => 'string'];
        return ['type' => 'object', 'properties' => $properties, 'required' => self::OUTPUT_FIELDS, 'additionalProperties' => false];
    }

    private function errorMessage(Response $response, string $provider): string
    {
        $message = $response->json('error.message')
            ?? $response->json('message')
            ?? $this->decodeRawError($response->json('error.metadata.raw'))
            ?? $this->decodeRawError($response->body())
            ?? ucfirst($provider).' AI request failed.';

        return trim((string) $message);
    }

    private function decodeRawError(mixed $raw): ?string
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return data_get($data, 'error.message')
                ?? data_get($data, 'message')
                ?? (is_string(data_get($data, 'error')) ? data_get($data, 'error') : null);
        } catch (JsonException) {
            return str_starts_with(trim($raw), '{') ? null : trim($raw);
        }
    }

    private function prompt(Lead $lead): string
    {
        return "Analyze this business lead for website development.\nBusiness: {$lead->business_name}\nCategory: {$lead->business_category}\nLocation: {$lead->city}, {$lead->province}, {$lead->country}\nRating: {$lead->rating}\nReviews: {$lead->total_reviews}\nWebsite status: {$lead->website_status}\nPhone available: ".($lead->phone ? 'yes' : 'no')."\nReturn JSON with every one of these keys and no empty strings: ".implode(', ', self::OUTPUT_FIELDS).". lead_score must be 0-100 and lead_quality must be Hot, Warm, or Cold. Generate complete outreach for WhatsApp English, localized WhatsApp, email_subject, email_body, and call_script. Put a concise localized message for the lead's country in whatsapp_message_roman_urdu. Messages must be polite, natural, non-spammy, and specific to the category.";
    }
}
