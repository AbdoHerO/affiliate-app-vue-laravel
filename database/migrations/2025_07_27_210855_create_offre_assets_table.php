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
        Schema::create('offre_assets', function (Blueprint $table) {
            $table->foreignUuid('offre_id')->constrained('offres')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets_marketing')->cascadeOnDelete();

            $table->primary(['offre_id', 'asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offre_assets');
    }
};
