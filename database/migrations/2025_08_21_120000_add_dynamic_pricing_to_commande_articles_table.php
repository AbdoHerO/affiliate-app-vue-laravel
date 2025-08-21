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
        Schema::table('commande_articles', function (Blueprint $table) {
            // Add sell_price column - the final price chosen by affiliate at order time
            $table->decimal('sell_price', 12, 2)->nullable()->after('prix_unitaire')
                ->comment('Final sell price chosen by affiliate at order time');
            
            // Add commission_amount column - computed commission for this item
            $table->decimal('commission_amount', 12, 2)->nullable()->after('sell_price')
                ->comment('Computed commission amount (sell_price - cost_price) * quantity');
            
            // Add commission_rule_code column - for future commission rule tracking
            $table->string('commission_rule_code', 50)->nullable()->after('commission_amount')
                ->comment('Code of commission rule applied (for future use)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commande_articles', function (Blueprint $table) {
            $table->dropColumn(['sell_price', 'commission_amount', 'commission_rule_code']);
        });
    }
};
