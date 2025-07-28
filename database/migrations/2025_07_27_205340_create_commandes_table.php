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
        Schema::create('commandes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('boutique_id')->constrained('boutiques')->cascadeOnDelete();
            $table->foreignUuid('affilie_id')->constrained('profils_affilies')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('adresse_id')->constrained('adresses')->restrictOnDelete();
            $table->foreignUuid('offre_id')->nullable()->constrained('offres')->nullOnDelete();
            $table->string('statut')->default('en_attente')->comment('allowed: en_attente,confirmee,expediee,livree,annulee,retournee,echec_livraison');
            $table->string('confirmation_cc')->default('a_confirmer')->comment('allowed: non_contacte,a_confirmer,confirme,injoignable');
            $table->string('mode_paiement')->default('cod')->comment('allowed: cod');
            $table->decimal('total_ht', 12, 2)->default(0);
            $table->decimal('total_ttc', 12, 2)->default(0);
            $table->string('devise', 3)->default('MAD');
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
