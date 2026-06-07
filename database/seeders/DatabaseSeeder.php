<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin/User', 'guard_name' => 'web']);

        $user = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'LeadPilot Admin', 'password' => '123456']
        );
        $user->syncRoles([$superAdmin]);

        Service::firstOrCreate(
            ['user_id' => $user->id, 'service_name' => 'Business Website Development'],
            [
                'category' => 'Web Development',
                'description' => 'Professional websites for local businesses with services, gallery, contact form, WhatsApp and Google Maps.',
                'target_customer_type' => 'Local businesses without websites',
                'base_offer' => 'A fast, mobile-friendly business website that turns Maps visitors into inquiries.',
                'price_range' => 'PKR 45,000 - 150,000',
                'keywords' => ['business website', 'restaurant website', 'clinic website', 'school website'],
                'is_active' => true,
            ]
        );
    }
}
