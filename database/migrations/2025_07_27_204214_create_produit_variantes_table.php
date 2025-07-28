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
        Schema::create('produit_variantes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->string('nom')->comment('ex: couleur');
            $table->string('valeur')->comment('ex: rouge');
            $table->decimal('prix_vente_variante', 12, 2)->nullable();
            $table->text('image_url')->nullable();
            $table->boolean('actif')->default(true);

            $table->unique(['produit_id', 'nom', 'valeur']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produit_variantes');
    }
};
