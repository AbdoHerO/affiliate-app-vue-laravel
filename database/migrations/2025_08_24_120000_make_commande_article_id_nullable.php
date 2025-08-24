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
        Schema::table('commissions_affilies', function (Blueprint $table) {
            // Make commande_article_id nullable for order-level commissions
            $table->foreignUuid('commande_article_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_affilies', function (Blueprint $table) {
            // Restore commande_article_id to NOT NULL
            $table->foreignUuid('commande_article_id')->nullable(false)->change();
        });
    }
};
