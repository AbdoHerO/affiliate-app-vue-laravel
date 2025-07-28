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
        Schema::create('avis_produits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->foreignUuid('auteur_id')->constrained('users')->restrictOnDelete();
            $table->integer('note');
            $table->text('commentaire')->nullable();
            $table->string('statut')->default('en_attente')->comment('allowed: en_attente,approuve,refuse');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avis_produits');
    }
};
