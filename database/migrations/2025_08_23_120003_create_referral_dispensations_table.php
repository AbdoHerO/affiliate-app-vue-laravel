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
        Schema::create('referral_dispensations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_affiliate_id')->constrained('profils_affilies')->cascadeOnDelete();
            $table->integer('points')->comment('Points awarded to the referrer');
            $table->text('comment')->comment('Admin comment explaining the dispensation');
            $table->string('reference', 100)->nullable()->comment('External reference or campaign code');
            $table->foreignUuid('created_by_admin_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['referrer_affiliate_id', 'created_at']);
            $table->index('created_by_admin_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_dispensations');
    }
};
