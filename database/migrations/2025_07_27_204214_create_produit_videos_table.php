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
        Schema::create('produit_videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->text('url');
            $table->string('titre')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produit_videos');
    }
};
