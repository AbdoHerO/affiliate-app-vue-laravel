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
        Schema::create('withdrawal_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('withdrawal_id')->constrained('withdrawals')->cascadeOnDelete();
            $table->foreignUuid('commission_id')->constrained('commissions_affilies')->restrictOnDelete();
            
            // Amount from commission at time of withdrawal
            $table->decimal('amount', 12, 2);
            
            $table->timestampsTz();
            
            // Ensure one commission can only be in one withdrawal
            $table->unique('commission_id');
            
            // Index for performance
            $table->index('withdrawal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_items');
    }
};
