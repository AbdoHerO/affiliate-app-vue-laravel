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
            $table->uuid('attribut_id')->nullable();
            $table->uuid('valeur_id')->nullable();
            $table->foreign('attribut_id')->references('id')->on('variant_attributs')->onDelete('set null');
            $table->foreign('valeur_id')->references('id')->on('variant_valeurs')->onDelete('set null');

            // Unique constraint to prevent duplicate combinations per product
            $table->unique(['produit_id', 'attribut_id', 'valeur_id'], 'unique_product_variant_combination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produit_variantes', function (Blueprint $table) {
            $table->dropForeign(['attribut_id']);
            $table->dropForeign(['valeur_id']);
            $table->dropUnique('unique_product_variant_combination');
            $table->dropColumn(['attribut_id', 'valeur_id']);
        });
    }
};
