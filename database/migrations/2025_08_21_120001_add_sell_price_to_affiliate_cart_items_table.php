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
        Schema::table('affiliate_cart_items', function (Blueprint $table) {
            // Add sell_price column - the price chosen by affiliate for this item
            $table->decimal('sell_price', 12, 2)->nullable()->after('qty')
                ->comment('Sell price chosen by affiliate (defaults to product prix_vente)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_cart_items', function (Blueprint $table) {
            $table->dropColumn('sell_price');
        });
    }
};
