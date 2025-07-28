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
        Schema::create('encaissements_cod', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('expedition_id')->constrained('expeditions')->cascadeOnDelete();
            $table->integer('tentative_no')->default(1);
            $table->decimal('montant', 12, 2);
            $table->string('statut')->comment('allowed: recu,partiel,echoue');
            $table->timestampTz('recu_at')->nullable();
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encaissements_cod');
    }
};
