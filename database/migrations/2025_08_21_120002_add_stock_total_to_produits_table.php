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
            // Add stock_total column after quantite_min
            if (!Schema::hasColumn('produits', 'stock_total')) {
                $table->integer('stock_total')->nullable()->default(0)->after('quantite_min')
                    ->comment('Total stock quantity for this product');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('stock_total');
        });
    }
};
