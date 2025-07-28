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
        Schema::create('offres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('boutique_id')->constrained('boutiques')->cascadeOnDelete();
            $table->foreignUuid('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->foreignUuid('variante_id')->nullable()->constrained('produit_variantes')->nullOnDelete();
            $table->string('titre_public');
            $table->decimal('prix_vente', 12, 2)->comment('prix affiché au client final');
            $table->boolean('actif')->default(true);
            $table->timestampTz('date_debut')->nullable();
            $table->timestampTz('date_fin')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offres');
    }
};
