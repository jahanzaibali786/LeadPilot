<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_authenticated_pages_render(): void
    {
        $user = User::factory()->create();
        $service = Service::create(['user_id'=>$user->id,'service_name'=>'Websites','category'=>'Web Development']);
        $campaign = Campaign::create(['user_id'=>$user->id,'service_id'=>$service->id,'title'=>'Test campaign','city'=>'Lahore','business_category'=>'Restaurant']);
        $lead = Lead::create(['user_id'=>$user->id,'service_id'=>$service->id,'campaign_id'=>$campaign->id,'business_name'=>'Test Restaurant','city'=>'Lahore','website'=>'https://test-restaurant.example','has_website'=>true,'website_status'=>'Has Website','lead_score'=>90,'lead_quality'=>'Hot']);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Dashboard');
        $this->get('/campaigns')
            ->assertOk()
            ->assertSee('Test campaign')
            ->assertSee(route('campaigns.create'), false)
            ->assertDontSee('campaign-location-map', false);
        $this->get(route('campaigns.create'))
            ->assertOk()
            ->assertSee('campaign-country-code', false)
            ->assertSee('campaign-city', false)
            ->assertSee('campaign-location-map', false)
            ->assertSee('campaign-radius', false);
        $this->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('campaign-progress-bar', false)
            ->assertSee('run-campaign-form', false)
            ->assertSee('Collected leads');
        foreach (range(1, 20) as $index) {
            Lead::create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'campaign_id' => $campaign->id,
                'business_name' => "Test Restaurant $index",
                'city' => 'Lahore',
                'lead_score' => 80,
                'lead_quality' => 'Warm',
            ]);
        }
        $this->get('/leads')
            ->assertOk()
            ->assertSee('Test Restaurant')
            ->assertSee('auto-submit-filter', false)
            ->assertSee('requestSubmit', false)
            ->assertSee('pagination', false)
            ->assertSee('page-link', false)
            ->assertSee('Visit website')
            ->assertSee('https://test-restaurant.example', false);
        $this->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('ai-pitch-form', false)
            ->assertSee('ai-pitch-progress', false)
            ->assertSee('ai-pitch-live-text', false)
            ->assertSee('lead-whatsapp-english', false)
            ->assertSee('https://test-restaurant.example', false);
        $this->get('/leads/board')
            ->assertOk()
            ->assertSee('Sales pipeline')
            ->assertSee('auto-submit-filter', false)
            ->assertSee('requestSubmit', false)
            ->assertSee('Unique all stages')
            ->assertSee('stage-unique-button', false);
    }
}
