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
        Schema::create('retour_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('retour_id')->constrained('retours')->cascadeOnDelete();
            $table->foreignUuid('commande_article_id')->constrained('commande_articles')->cascadeOnDelete();
            $table->integer('quantite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retour_articles');
    }
};
