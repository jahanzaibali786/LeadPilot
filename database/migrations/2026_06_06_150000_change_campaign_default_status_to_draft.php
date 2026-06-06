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
            $table->string('status')->default('draft')->change();
        });

        DB::table('campaigns')
            ->where('status', 'pending')
            ->whereNull('started_at')
            ->update(['status' => 'draft']);
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }
};
