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
        Schema::create('produit_ruptures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variante_id')->constrained('produit_variantes')->cascadeOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produit_ruptures');
    }
};
