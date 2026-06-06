<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('service_name');
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('target_customer_type')->nullable();
            $table->text('base_offer')->nullable();
            $table->string('price_range')->nullable();
            $table->json('keywords')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type')->default('api');
            $table->string('base_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source_type')->default('google_places');
            $table->string('country')->default('Pakistan');
            $table->string('province')->nullable();
            $table->string('city');
            $table->string('business_category');
            $table->string('keyword')->nullable();
            $table->decimal('minimum_rating', 3, 1)->default(0);
            $table->unsignedInteger('minimum_reviews')->default(0);
            $table->boolean('only_without_website')->default(true);
            $table->boolean('only_with_phone')->default(true);
            $table->unsignedInteger('required_leads')->default(50);
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->unsignedInteger('total_found')->default(0);
            $table->unsignedInteger('total_saved')->default(0);
            $table->unsignedInteger('duplicates_removed')->default(0);
            $table->unsignedInteger('valid_leads')->default(0);
            $table->unsignedInteger('hot_leads')->default(0);
            $table->unsignedInteger('warm_leads')->default(0);
            $table->unsignedInteger('cold_leads')->default(0);
            $table->unsignedInteger('failed_requests')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('google_place_id')->nullable()->index();
            $table->string('business_name');
            $table->string('business_category')->nullable()->index();
            $table->string('owner_name')->nullable();
            $table->string('email')->nullable();
            $table->string('original_phone')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('formatted_phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('website')->nullable();
            $table->boolean('has_website')->default(false)->index();
            $table->string('website_status')->default('No Website')->index();
            $table->string('online_presence_status')->default('Google Listing Only');
            $table->text('google_maps_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('province')->nullable();
            $table->string('country')->default('Pakistan');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->unsignedInteger('total_reviews')->nullable();
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->unsignedTinyInteger('lead_score')->default(0)->index();
            $table->string('lead_quality')->nullable()->index();
            $table->string('opportunity_type')->nullable();
            $table->text('match_reason')->nullable();
            $table->text('suggested_offer')->nullable();
            $table->text('suggested_pitch')->nullable();
            $table->text('whatsapp_message_english')->nullable();
            $table->text('whatsapp_message_roman_urdu')->nullable();
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->text('call_script')->nullable();
            $table->string('contact_status')->default('New')->index();
            $table->string('board_status')->default('New')->index();
            $table->unsignedInteger('board_order')->default(0);
            $table->date('follow_up_date')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'board_status', 'board_order']);
            $table->index(['user_id', 'business_name', 'city']);
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('follow_up_date')->index();
            $table->time('follow_up_time')->nullable();
            $table->string('method');
            $table->text('note');
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });

        Schema::create('blacklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('value');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'type', 'value']);
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('filters');
            $table->timestamps();
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->string('status')->default('completed');
            $table->timestamps();
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('endpoint');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_meta')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('anthropic');
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->boolean('successful')->default(true);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'ai_logs', 'api_logs', 'exports', 'saved_searches', 'blacklists', 'settings', 'follow_ups', 'lead_notes', 'leads', 'campaigns', 'lead_sources', 'services'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
