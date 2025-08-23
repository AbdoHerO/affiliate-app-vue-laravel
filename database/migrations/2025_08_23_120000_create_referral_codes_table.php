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
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->constrained('profils_affilies')->cascadeOnDelete();
            $table->string('code', 20)->unique()->comment('Unique referral code for the affiliate');
            $table->boolean('active')->default(true)->comment('Whether the referral code is active');
            $table->timestampsTz();

            $table->index(['code', 'active']);
            $table->index('affiliate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
