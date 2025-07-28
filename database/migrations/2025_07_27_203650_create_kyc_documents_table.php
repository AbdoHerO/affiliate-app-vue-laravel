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
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('utilisateur_id')->constrained('users')->cascadeOnDelete();
            $table->string('type_doc')->comment('allowed: cni,passport,rib,contrat');
            $table->text('url_fichier');
            $table->string('statut')->default('en_attente')->comment('allowed: en_attente,valide,refuse');
            $table->text('motif_refus')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
