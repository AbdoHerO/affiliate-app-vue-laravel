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
        Schema::create('stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variante_id')->constrained('produit_variantes')->cascadeOnDelete();
            $table->foreignUuid('entrepot_id')->constrained('entrepots')->cascadeOnDelete();
            $table->integer('qte_disponible')->default(0);
            $table->integer('qte_reservee')->default(0);

            $table->unique(['variante_id', 'entrepot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
