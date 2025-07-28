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
        Schema::create('commissions_affilies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('commande_article_id')->constrained('commande_articles')->cascadeOnDelete();
            $table->foreignUuid('affilie_id')->constrained('profils_affilies')->restrictOnDelete();
            $table->string('type')->comment('allowed: vente,parrainage,bonus');
            $table->decimal('montant', 12, 2);
            $table->string('statut')->default('en_attente')->comment('allowed: en_attente,valide,paye,annule');
            $table->text('motif')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions_affilies');
    }
};
