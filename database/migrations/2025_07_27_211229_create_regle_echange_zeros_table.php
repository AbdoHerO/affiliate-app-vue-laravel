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
        Schema::create('regles_echange_zero', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('produit_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('gamme_id')->nullable()->constrained('gammes_affilies')->nullOnDelete();
            $table->integer('nb_max')->default(1)->comment('nombre max d\'échanges gratuits');
            $table->boolean('actif')->default(true);

            $table->unique(['produit_id', 'client_id', 'gamme_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regles_echange_zero');
    }
};
