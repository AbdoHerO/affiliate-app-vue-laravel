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
        Schema::create('profils_affilies_gamme_histo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profil_id')->constrained('profils_affilies')->cascadeOnDelete();
            $table->foreignUuid('gamme_id')->constrained('gammes_affilies')->cascadeOnDelete();
            $table->timestampTz('date_debut')->useCurrent();
            $table->timestampTz('date_fin')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profils_affilies_gamme_histo');
    }
};
