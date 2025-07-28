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
        Schema::create('reglements_affilies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('affilie_id')->constrained('profils_affilies')->restrictOnDelete();
            $table->decimal('montant_total', 12, 2);
            $table->string('statut')->default('en_attente')->comment('allowed: en_attente,traite,paye,annule');
            $table->string('mode_versement')->comment('allowed: virement,cheque,especes');
            $table->string('reference_ext')->nullable()->comment('référence banque/chèque');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reglements_affilies');
    }
};
