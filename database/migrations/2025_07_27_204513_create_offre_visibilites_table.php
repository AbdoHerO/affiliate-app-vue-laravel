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
        Schema::create('offre_visibilite', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('offre_id')->unique()->constrained('offres')->cascadeOnDelete();
            $table->string('mode')->default('public')->comment('allowed: public,prive_affilie,restreint_gamme');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offre_visibilite');
    }
};
