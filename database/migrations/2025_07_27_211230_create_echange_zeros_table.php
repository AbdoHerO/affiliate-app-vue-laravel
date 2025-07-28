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
        Schema::create('echanges_zero', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('regle_id')->constrained('regles_echange_zero')->cascadeOnDelete();
            $table->foreignUuid('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignUuid('produit_id')->constrained('produits')->restrictOnDelete();
            $table->foreignUuid('applique_par')->constrained('users')->restrictOnDelete();
            $table->text('motif')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('echanges_zero');
    }
};
