<?php
namespace Tests\Feature;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class LeadStatusTest extends TestCase {
    use RefreshDatabase;
    public function test_owner_can_move_lead_to_won(): void {
        $user=User::factory()->create(); $lead=Lead::create(['user_id'=>$user->id,'business_name'=>'Test Business','board_status'=>'New']);
        $this->actingAs($user)->patchJson(route('leads.status',$lead),['status'=>'Won','board_order'=>2])->assertOk();
        $this->assertDatabaseHas('leads',['id'=>$lead->id,'board_status'=>'Won','contact_status'=>'Won']); $this->assertNotNull($lead->fresh()->converted_at);
    }
    public function test_other_user_cannot_move_lead(): void {
        $owner=User::factory()->create(); $other=User::factory()->create(); $lead=Lead::create(['user_id'=>$owner->id,'business_name'=>'Private Lead']);
        $this->actingAs($other)->patchJson(route('leads.status',$lead),['status'=>'Won'])->assertForbidden();
    }
}
