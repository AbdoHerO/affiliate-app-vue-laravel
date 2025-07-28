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
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auteur_id')->constrained('users')->restrictOnDelete();
            $table->string('sujet');
            $table->string('priorite')->default('normale')->comment('allowed: basse,normale,haute,urgente');
            $table->string('statut')->default('ouvert')->comment('allowed: ouvert,en_cours,resolu,ferme');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
