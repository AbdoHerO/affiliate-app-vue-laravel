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
        Schema::create('referral_attributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('new_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('referrer_affiliate_id')->constrained('profils_affilies')->cascadeOnDelete();
            $table->string('referral_code', 20)->comment('The referral code used');
            $table->timestampTz('attributed_at')->comment('When the attribution was created');
            $table->boolean('verified')->default(false)->comment('Whether the new user has been verified');
            $table->timestampTz('verified_at')->nullable()->comment('When the user was verified');
            $table->string('source', 50)->default('web')->comment('Source: web, mobile, etc.');
            $table->string('ip_hash', 64)->comment('Hashed IP address for fraud detection');
            $table->json('device_fingerprint')->nullable()->comment('Device fingerprint data');
            $table->timestampsTz();

            $table->index(['referrer_affiliate_id', 'attributed_at']);
            $table->index(['new_user_id']);
            $table->index(['referral_code', 'attributed_at']);
            $table->index(['verified', 'verified_at']);
            $table->index('attributed_at');

            $table->foreign('referral_code')->references('code')->on('referral_codes')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_attributions');
    }
};
