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
        Schema::create('variant_valeurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribut_id')->constrained('variant_attributs')->onDelete('cascade');
            $table->string('code');
            $table->string('libelle');
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();

            $table->unique(['attribut_id', 'code']);
            $table->index(['attribut_id', 'actif', 'ordre']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_valeurs');
    }
};
