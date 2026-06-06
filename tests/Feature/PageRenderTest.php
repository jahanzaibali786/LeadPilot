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
        Lead::create(['user_id'=>$user->id,'service_id'=>$service->id,'campaign_id'=>$campaign->id,'business_name'=>'Test Restaurant','city'=>'Lahore','lead_score'=>90,'lead_quality'=>'Hot']);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Command center');
        $this->get('/campaigns')->assertOk()->assertSee('Test campaign');
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
            ->assertSee('pagination', false)
            ->assertSee('page-link', false);
        $this->get('/leads/board')
            ->assertOk()
            ->assertSee('Sales pipeline')
            ->assertSee('Unique all stages')
            ->assertSee('stage-unique-button', false);
    }
}
