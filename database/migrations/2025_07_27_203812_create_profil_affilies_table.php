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
        Schema::create('profils_affilies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('utilisateur_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('gamme_id')->nullable()->constrained('gammes_affilies')->nullOnDelete();
            $table->integer('points')->default(0);
            $table->string('statut')->default('actif')->comment('allowed: actif,suspendu,resilie');
            $table->string('rib')->nullable();
            $table->text('notes_interne')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profils_affilies');
    }
};
