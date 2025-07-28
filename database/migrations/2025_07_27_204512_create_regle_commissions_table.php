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
        Schema::create('regles_commission', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('offre_id')->constrained('offres')->cascadeOnDelete();
            $table->string('pays_code', 2)->nullable()->comment('NULL = tous pays de l\'offre');
            $table->foreignUuid('gamme_id')->nullable()->constrained('gammes_affilies')->nullOnDelete()->comment('NULL = toutes gammes');
            $table->string('type')->comment('allowed: pourcentage,fixe');
            $table->decimal('valeur', 12, 4)->comment('si % => 0..100 ; si fixe => montant');
            $table->boolean('actif')->default(true);

            $table->unique(['offre_id', 'pays_code', 'gamme_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regles_commission');
    }
};
