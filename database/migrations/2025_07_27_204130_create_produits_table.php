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
        Schema::create('produits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('boutique_id')->constrained('boutiques')->cascadeOnDelete();
            $table->foreignUuid('categorie_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->decimal('prix_achat', 12, 2)->default(0);
            $table->decimal('prix_vente', 12, 2);
            $table->decimal('prix_affilie', 12, 2)->nullable()->comment('montant fixe par vente (optionnel si %)');
            $table->string('slug')->unique();
            $table->boolean('actif')->default(true);
            $table->integer('quantite_min')->default(1);
            $table->text('notes_admin')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
