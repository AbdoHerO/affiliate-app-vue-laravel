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
        Schema::create('affiliate_email_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('affilie_id');
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            $table->timestampsTz();

            $table->foreign('affilie_id')->references('id')->on('affilies')->onDelete('cascade');
            $table->index(['token', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_email_verifications');
    }
};
