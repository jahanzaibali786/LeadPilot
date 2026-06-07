<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->text('website')->nullable()->change();
            $table->text('facebook_url')->nullable()->change();
            $table->text('instagram_url')->nullable()->change();
            $table->text('linkedin_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('website')->nullable()->change();
            $table->string('facebook_url')->nullable()->change();
            $table->string('instagram_url')->nullable()->change();
            $table->string('linkedin_url')->nullable()->change();
        });
    }
};
