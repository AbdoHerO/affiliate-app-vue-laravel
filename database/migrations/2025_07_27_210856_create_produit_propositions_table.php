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
        Schema::create('produit_propositions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('produit_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->foreignUuid('auteur_id')->constrained('users')->restrictOnDelete();
            $table->string('type')->comment('allowed: nouveau,modification,suppression');
            $table->text('description');
            $table->string('statut')->default('en_attente')->comment('allowed: en_attente,approuve,refuse');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produit_propositions');
    }
};
