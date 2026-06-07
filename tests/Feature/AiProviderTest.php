<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_credentials_are_encrypted_selected_and_masked_in_settings(): void
    {
        $user = User::factory()->create();
        $key = 'sk-or-test-secret-provider-key';

        $this->actingAs($user)->put(route('settings.update'), [
            'ai_provider' => 'openrouter',
            'openrouter_api_key' => $key,
            'ai_model_openrouter' => 'openrouter/free',
        ])->assertRedirect();

        $setting = Setting::where('user_id', $user->id)->where('key', 'openrouter_api_key')->firstOrFail();

        $this->assertTrue((bool) $setting->is_encrypted);
        $this->assertNotSame($key, $setting->value);
        $this->assertSame($key, Crypt::decryptString($setting->value));
        $this->assertSame('openrouter', app(AiCredentialService::class)->providerForUser($user->id));

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('OpenRouter')
            ->assertSee('Saved in Settings')
            ->assertDontSee($key);
    }

    public function test_lead_analysis_uses_selected_openrouter_provider_and_updates_lead(): void
    {
        $user = User::factory()->create();
        $lead = Lead::create([
            'user_id' => $user->id,
            'business_name' => 'Example Restaurant',
            'business_category' => 'Restaurant',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'rating' => 4.4,
            'total_reviews' => 90,
        ]);
        Setting::create(['user_id' => $user->id, 'key' => 'ai_provider', 'value' => 'openrouter']);
        Setting::create([
            'user_id' => $user->id,
            'key' => 'openrouter_api_key',
            'value' => Crypt::encryptString('saved-openrouter-key'),
            'is_encrypted' => true,
        ]);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'test/free-model',
                'choices' => [['message' => ['content' => json_encode($this->analysisPayload())]]],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 30],
            ]),
        ]);

        $this->actingAs($user)->postJson(route('leads.ai', $lead))
            ->assertOk()
            ->assertJsonPath('message', 'Pitch generated.')
            ->assertJsonPath('lead.lead_score', 88)
            ->assertJsonPath('lead.lead_quality', 'Hot')
            ->assertJsonPath('lead.whatsapp_message_english', 'Hello, I have an idea for your website.')
            ->assertJsonPath('lead.email_subject', 'Website idea')
            ->assertJsonPath('lead.email_body', 'A concise email.')
            ->assertJsonPath('lead.call_script', 'A concise call script.');

        $lead->refresh();
        $this->assertSame(88, $lead->lead_score);
        $this->assertSame('Hot', $lead->lead_quality);
        $this->assertDatabaseHas('ai_logs', [
            'lead_id' => $lead->id,
            'provider' => 'openrouter',
            'model' => 'test/free-model',
            'successful' => true,
        ]);
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer saved-openrouter-key')
        );
    }

    public function test_gemini_provider_parses_generate_content_response(): void
    {
        $user = User::factory()->create();
        $lead = Lead::create(['user_id' => $user->id, 'business_name' => 'Gemini Lead']);
        Setting::create(['user_id' => $user->id, 'key' => 'ai_provider', 'value' => 'gemini']);
        Setting::create([
            'user_id' => $user->id,
            'key' => 'gemini_api_key',
            'value' => Crypt::encryptString('saved-gemini-key'),
            'is_encrypted' => true,
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($this->analysisPayload())]]]]],
                'usageMetadata' => ['promptTokenCount' => 15, 'candidatesTokenCount' => 25],
            ]),
        ]);

        $this->actingAs($user)->postJson(route('leads.ai', $lead))
            ->assertOk()
            ->assertJsonPath('message', 'Pitch generated.')
            ->assertJsonPath('lead.lead_score', 88)
            ->assertJsonPath('lead.lead_quality', 'Hot')
            ->assertJsonPath('lead.whatsapp_message_english', 'Hello, I have an idea for your website.')
            ->assertJsonPath('lead.email_subject', 'Website idea')
            ->assertJsonPath('lead.email_body', 'A concise email.')
            ->assertJsonPath('lead.call_script', 'A concise call script.');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'lead_score' => 88, 'lead_quality' => 'Hot']);
        Http::assertSent(fn ($request) =>
            str_contains($request->url(), 'gemini-2.5-flash:generateContent')
            && $request->header('x-goog-api-key')[0] === 'saved-gemini-key'
        );
    }

    public function test_openrouter_pitch_falls_back_when_selected_model_provider_fails(): void
    {
        $user = User::factory()->create();
        $lead = Lead::create([
            'user_id' => $user->id,
            'business_name' => 'Fallback Restaurant',
            'business_category' => 'Restaurant',
            'city' => 'Lahore',
            'country' => 'Pakistan',
        ]);
        Setting::create(['user_id' => $user->id, 'key' => 'ai_provider', 'value' => 'openrouter']);
        Setting::create(['user_id' => $user->id, 'key' => 'ai_model_openrouter', 'value' => 'openrouter/free']);
        Setting::create([
            'user_id' => $user->id,
            'key' => 'openrouter_api_key',
            'value' => Crypt::encryptString('saved-openrouter-key'),
            'is_encrypted' => true,
        ]);
        $calls = 0;
        Http::fake(function ($request) use (&$calls) {
            $calls++;
            if ($calls === 1) {
                return Http::response(['error' => ['message' => 'Provider returned error']], 502);
            }

            return Http::response([
                'model' => 'nvidia/nemotron-nano-9b-v2:free',
                'choices' => [['message' => ['content' => json_encode($this->analysisPayload())]]],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 30],
            ]);
        });

        $this->actingAs($user)->postJson(route('leads.ai', $lead))
            ->assertOk()
            ->assertJsonPath('lead.lead_score', 88);

        $this->assertSame(2, $calls);
        $this->assertDatabaseHas('ai_logs', [
            'lead_id' => $lead->id,
            'provider' => 'openrouter',
            'model' => 'openrouter/free',
            'successful' => false,
            'error' => 'Provider returned error',
        ]);
        $this->assertDatabaseHas('ai_logs', [
            'lead_id' => $lead->id,
            'provider' => 'openrouter',
            'model' => 'nvidia/nemotron-nano-9b-v2:free',
            'successful' => true,
        ]);
    }
    public function test_blank_email_and_call_script_are_filled_from_lead_context(): void
    {
        $user = User::factory()->create();
        $lead = Lead::create([
            'user_id' => $user->id,
            'business_name' => 'Complete Pitch Travels',
            'business_category' => 'Travel Agency',
            'city' => 'Rawalpindi',
            'country' => 'Pakistan',
        ]);
        Setting::create(['user_id' => $user->id, 'key' => 'ai_provider', 'value' => 'openrouter']);
        Setting::create([
            'user_id' => $user->id,
            'key' => 'openrouter_api_key',
            'value' => Crypt::encryptString('saved-openrouter-key'),
            'is_encrypted' => true,
        ]);
        $payload = $this->analysisPayload();
        $payload['email_subject'] = '';
        $payload['email_body'] = '';
        $payload['call_script'] = '';
        Http::fake(['openrouter.ai/*' => Http::response([
            'model' => 'test/free-model',
            'choices' => [['message' => ['content' => json_encode($payload)]]],
        ])]);

        $this->actingAs($user)->postJson(route('leads.ai', $lead))
            ->assertOk()
            ->assertJsonPath('message', 'Pitch generated.')
            ->assertJsonPath('lead.email_subject', 'Website idea for Complete Pitch Travels')
            ->assertJson(fn (\Illuminate\Testing\Fluent\AssertableJson $json) => $json
                ->where('lead.call_script', fn ($value) => filled($value) && str_contains($value, 'Complete Pitch Travels'))
                ->where('lead.email_body', fn ($value) => filled($value) && str_contains($value, 'Travel Agency'))
                ->etc()
            );
    }
    private function analysisPayload(): array
    {
        return [
            'lead_score' => 88,
            'lead_quality' => 'Hot',
            'opportunity_type' => 'Website redesign',
            'match_reason' => 'Strong local reputation and no effective website.',
            'suggested_offer' => 'Conversion-focused restaurant website.',
            'suggested_pitch' => 'Turn local searches into bookings.',
            'whatsapp_message_english' => 'Hello, I have an idea for your website.',
            'whatsapp_message_roman_urdu' => 'Assalam o Alaikum, website ke liye aik idea hai.',
            'email_subject' => 'Website idea',
            'email_body' => 'A concise email.',
            'call_script' => 'A concise call script.',
        ];
    }
}
