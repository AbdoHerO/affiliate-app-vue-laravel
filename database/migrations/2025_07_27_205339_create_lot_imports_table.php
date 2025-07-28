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
        Schema::create('lots_import', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('affilie_id')->constrained('profils_affilies')->cascadeOnDelete();
            $table->string('source')->comment('csv,excel,api,manuel');
            $table->string('fichier_nom')->nullable();
            $table->integer('total_lignes')->default(0);
            $table->integer('lignes_ok')->default(0);
            $table->integer('lignes_ko')->default(0);
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots_import');
    }
};
