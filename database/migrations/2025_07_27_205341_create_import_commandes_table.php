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
        Schema::create('import_commandes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lot_id')->constrained('lots_import')->cascadeOnDelete();
            $table->jsonb('brut_payload');
            $table->string('validation_statut')->comment('allowed: valide,invalide,warning');
            $table->text('validation_erreurs')->nullable();
            $table->foreignUuid('commande_id')->nullable()->constrained('commandes')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_commandes');
    }
};
