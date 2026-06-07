<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\GooglePlacesCredentialService;
use App\Services\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_encrypted_google_places_key_and_settings_masks_it(): void
    {
        config(['services.google_places.key' => 'environment-fallback-key']);
        $user = User::factory()->create();
        $key = 'AIza-user-saved-secret-key-123456789';

        $this->actingAs($user)->put(route('settings.update'), [
            'google_places_api_key' => $key,
        ])->assertRedirect();

        $setting = Setting::where('user_id', $user->id)
            ->where('key', GooglePlacesCredentialService::SETTING_KEY)
            ->firstOrFail();

        $this->assertTrue((bool) $setting->is_encrypted);
        $this->assertNotSame($key, $setting->value);
        $this->assertSame($key, Crypt::decryptString($setting->value));
        $this->assertSame($key, app(GooglePlacesCredentialService::class)->forUser($user->id));

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Saved in Settings')
            ->assertDontSee($key);
    }

    public function test_google_places_request_uses_campaign_owners_saved_key(): void
    {
        config([
            'services.google_places.key' => 'environment-fallback-key',
            'lead-generator.google_places_max_queries' => 1,
        ]);
        $user = User::factory()->create();
        $savedKey = 'AIza-campaign-owner-key-123456789';
        Setting::create([
            'user_id' => $user->id,
            'key' => GooglePlacesCredentialService::SETTING_KEY,
            'value' => Crypt::encryptString($savedKey),
            'is_encrypted' => true,
        ]);
        $service = Service::create([
            'user_id' => $user->id,
            'service_name' => 'Websites',
            'category' => 'Web Development',
        ]);
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'title' => 'Saved key campaign',
            'city' => 'Lahore',
            'business_category' => 'Restaurant',
            'required_leads' => 1,
        ]);
        Http::fake(['*' => Http::response(['places' => []])]);

        app(GooglePlacesService::class)->searchCampaignBusinesses($campaign);

        Http::assertSent(fn ($request) => $request->header('X-Goog-Api-Key')[0] === $savedKey);
    }

    public function test_removing_saved_key_restores_environment_fallback(): void
    {
        config(['services.google_places.key' => 'environment-fallback-key']);
        $user = User::factory()->create();
        Setting::create([
            'user_id' => $user->id,
            'key' => GooglePlacesCredentialService::SETTING_KEY,
            'value' => Crypt::encryptString('saved-user-key-123456789'),
            'is_encrypted' => true,
        ]);

        $this->actingAs($user)->put(route('settings.update'), [
            'remove_google_places_api_key' => '1',
        ])->assertRedirect();

        $this->assertSame('environment-fallback-key', app(GooglePlacesCredentialService::class)->forUser($user->id));
    }
}