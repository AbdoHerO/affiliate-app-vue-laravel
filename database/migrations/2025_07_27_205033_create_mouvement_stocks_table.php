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
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variante_id')->constrained('produit_variantes')->cascadeOnDelete();
            $table->foreignUuid('entrepot_id')->constrained('entrepots')->cascadeOnDelete();
            $table->string('type')->comment('allowed: in,out,reservation,liberation,ajustement');
            $table->integer('quantite');
            $table->string('reference')->nullable()->comment('commande, retour, inventaire...');
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};
