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
        Schema::table('produits', function (Blueprint $table) {
            // Add stock_fake column after stock_total
            if (!Schema::hasColumn('produits', 'stock_fake')) {
                $table->integer('stock_fake')->nullable()->default(0)->after('stock_total')
                    ->comment('Fake stock quantity displayed to affiliates instead of real stock');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('stock_fake');
        });
    }
};
