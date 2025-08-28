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
            // Add SKU column after titre
            if (!Schema::hasColumn('produits', 'sku')) {
                $table->string('sku', 100)->nullable()->after('titre')
                    ->comment('Stock Keeping Unit - unique product identifier');
                
                // Add unique index for SKU (allowing nulls)
                $table->unique('sku', 'produits_sku_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            // Drop the unique index first
            $table->dropUnique('produits_sku_unique');
            // Then drop the column
            $table->dropColumn('sku');
        });
    }
};
