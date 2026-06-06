<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('country');
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('radius_meters')->default(10000)->after('longitude');
        });

        DB::table('campaigns')
            ->where('country', 'Pakistan')
            ->whereNull('country_code')
            ->update(['country_code' => 'PK']);
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'latitude', 'longitude', 'radius_meters']);
        });
    }
};
