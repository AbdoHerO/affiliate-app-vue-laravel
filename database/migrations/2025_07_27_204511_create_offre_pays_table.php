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
        Schema::create('offre_pays', function (Blueprint $table) {
            $table->foreignUuid('offre_id')->constrained('offres')->cascadeOnDelete();
            $table->string('pays_code', 2)->comment('ISO-3166 alpha-2');

            $table->primary(['offre_id', 'pays_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offre_pays');
    }
};
