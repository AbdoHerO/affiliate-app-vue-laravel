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
        Schema::create('expeditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignUuid('transporteur_id')->nullable()->constrained('transporteurs')->nullOnDelete();
            $table->string('tracking_no')->nullable();
            $table->string('statut')->default('preparee')->comment('allowed: preparee,en_cours,livree,retour,annulee,echec');
            $table->decimal('poids_kg', 10, 3)->nullable();
            $table->decimal('frais_transport', 12, 2)->default(0);
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expeditions');
    }
};
