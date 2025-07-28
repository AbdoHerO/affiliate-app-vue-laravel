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
        Schema::create('commande_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignUuid('produit_id')->constrained('produits')->restrictOnDelete();
            $table->foreignUuid('variante_id')->nullable()->constrained('produit_variantes')->nullOnDelete();
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 12, 2);
            $table->decimal('remise', 12, 2)->default(0);
            $table->decimal('total_ligne', 12, 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commande_articles');
    }
};
