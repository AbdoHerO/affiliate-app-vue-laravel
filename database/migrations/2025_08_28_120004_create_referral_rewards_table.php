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
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_affiliate_id')->constrained('profils_affilies')->cascadeOnDelete();
            $table->integer('points')->comment('Reward points given to the referrer');
            $table->text('comment')->comment('Admin comment explaining the reward');
            $table->string('reference', 100)->nullable()->comment('External reference or campaign code');
            $table->foreignUuid('created_by_admin_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            // Indexes
            $table->index(['referrer_affiliate_id', 'created_at']);
            $table->index('created_by_admin_id');
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
