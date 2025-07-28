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
        Schema::create('offre_visibilite_affilies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('offre_id')->constrained('offres')->cascadeOnDelete();
            $table->foreignUuid('affilie_id')->constrained('profils_affilies')->cascadeOnDelete();

            $table->unique(['offre_id', 'affilie_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offre_visibilite_affilies');
    }
};
