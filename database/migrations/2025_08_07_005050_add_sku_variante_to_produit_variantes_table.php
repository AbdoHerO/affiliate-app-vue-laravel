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
        Schema::table('produit_variantes', function (Blueprint $table) {
            $table->string('sku_variante', 100)->nullable()->after('prix_vente_variante');
            $table->index('sku_variante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produit_variantes', function (Blueprint $table) {
            $table->dropIndex(['sku_variante']);
            $table->dropColumn('sku_variante');
        });
    }
};
