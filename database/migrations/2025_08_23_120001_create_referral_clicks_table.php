<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('referral_code', 20)->comment('The referral code that was clicked');
            $table->string('ip_hash', 64)->comment('Hashed IP address for privacy');
            $table->text('user_agent')->nullable()->comment('User agent string');
            $table->string('referer_url')->nullable()->comment('Referring URL');
            $table->json('device_fingerprint')->nullable()->comment('Device fingerprint data');
            $table->timestampTz('clicked_at')->comment('When the click occurred');
            $table->timestampsTz();

            $table->index(['referral_code', 'clicked_at']);
            $table->index(['ip_hash', 'clicked_at']);
            $table->index('clicked_at');

            $table->foreign('referral_code')->references('code')->on('referral_codes')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_clicks');
    }
};
