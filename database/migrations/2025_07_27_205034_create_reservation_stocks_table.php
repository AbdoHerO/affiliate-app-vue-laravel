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
        Schema::create('reservations_stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variante_id')->constrained('produit_variantes')->cascadeOnDelete();
            $table->foreignUuid('entrepot_id')->constrained('entrepots')->cascadeOnDelete();
            $table->integer('quantite');
            $table->foreignUuid('gamme_id')->nullable()->constrained('gammes_affilies')->nullOnDelete()->comment('si réservé pour une gamme');
            $table->foreignUuid('affilie_id')->nullable()->constrained('profils_affilies')->nullOnDelete();
            $table->foreignUuid('offre_id')->nullable()->constrained('offres')->nullOnDelete();
            $table->timestampTz('date_expire')->nullable();
            $table->string('statut')->default('active')->comment('allowed: active,utilisee,expiree,annulee');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations_stock');
    }
};
