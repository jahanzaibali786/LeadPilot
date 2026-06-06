<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Service;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $superAdmin = Role::firstOrCreate(['name'=>'Super Admin','guard_name'=>'web']);
        Role::firstOrCreate(['name'=>'Admin/User','guard_name'=>'web']);
        $user = User::firstOrCreate(['email'=>'admin@gmail.com'],['name'=>'LeadPilot Admin','password'=>'123456']);
        $user->syncRoles([$superAdmin]);
        $service = Service::firstOrCreate(['user_id'=>$user->id,'service_name'=>'Business Website Development'],[
            'category'=>'Web Development','description'=>'Professional websites for Pakistani local businesses with services, gallery, contact form, WhatsApp and Google Maps.',
            'target_customer_type'=>'Local businesses without websites','base_offer'=>'A fast, mobile-friendly business website that turns Maps visitors into inquiries.',
            'price_range'=>'PKR 45,000 - 150,000','keywords'=>['business website','restaurant website','clinic website','school website'],'is_active'=>true,
        ]);
        $campaign = Campaign::firstOrCreate(['user_id'=>$user->id,'title'=>'Restaurants without websites in Abbottabad'],[
            'service_id'=>$service->id,'city'=>'Abbottabad','province'=>'Khyber Pakhtunkhwa','business_category'=>'Restaurant','keyword'=>'restaurants',
            'minimum_rating'=>3.5,'minimum_reviews'=>10,'required_leads'=>50,'status'=>'completed','progress_percentage'=>100,'total_found'=>3,'total_saved'=>3,'valid_leads'=>3,'hot_leads'=>2,'warm_leads'=>1,'started_at'=>now()->subHour(),'completed_at'=>now(),
        ]);
        foreach ([
            ['business_name'=>'Karakoram Family Restaurant','business_category'=>'Restaurant','phone'=>'923125551010','formatted_phone'=>'+92 312 5551010','whatsapp_number'=>'+923125551010','address'=>'Jinnah Road, Abbottabad','rating'=>4.4,'total_reviews'=>186,'lead_score'=>95,'lead_quality'=>'Hot','board_status'=>'New','contact_status'=>'New'],
            ['business_name'=>'Pine View Cafe','business_category'=>'Cafe','phone'=>'923335552020','formatted_phone'=>'+92 333 5552020','whatsapp_number'=>'+923335552020','address'=>'Supply Bazaar, Abbottabad','rating'=>4.1,'total_reviews'=>72,'lead_score'=>88,'lead_quality'=>'Hot','board_status'=>'Interested','contact_status'=>'Interested'],
            ['business_name'=>'Hazara BBQ Point','business_category'=>'Restaurant','phone'=>'923455553030','formatted_phone'=>'+92 345 5553030','whatsapp_number'=>'+923455553030','address'=>'Mandian, Abbottabad','rating'=>3.8,'total_reviews'=>31,'lead_score'=>72,'lead_quality'=>'Warm','board_status'=>'Follow Up','contact_status'=>'Follow Up','follow_up_date'=>now()->addDay()->toDateString()],
        ] as $i=>$data) {
            Lead::firstOrCreate(['user_id'=>$user->id,'business_name'=>$data['business_name'],'city'=>'Abbottabad'],array_merge($data,[
                'campaign_id'=>$campaign->id,'service_id'=>$service->id,'city'=>'Abbottabad','province'=>'Khyber Pakhtunkhwa','country'=>'Pakistan',
                'has_website'=>false,'website_status'=>'No Website','online_presence_status'=>'Google Listing + Phone','source_name'=>'Demo data',
                'opportunity_type'=>'New Website Opportunity','match_reason'=>'No website listed. Phone available. Strong local rating and review activity.',
                'suggested_offer'=>'Menu, gallery, WhatsApp ordering and Maps website','board_order'=>$i,
                'whatsapp_message_english'=>"Assalam o Alaikum, I found your business on Google Maps and noticed no website is listed. We build professional restaurant websites with menu, gallery, WhatsApp ordering and location. May I share a quick sample?",
                'whatsapp_message_roman_urdu'=>"Assalam o Alaikum, aapka business Google Maps par mila aur website listed nahi thi. Hum restaurants ke liye menu, gallery, WhatsApp order aur location wali website banate hain. Kya main ek short sample share karun?",
            ]));
        }
    }
}
