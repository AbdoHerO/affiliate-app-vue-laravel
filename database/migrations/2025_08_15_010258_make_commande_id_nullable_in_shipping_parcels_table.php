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
        Schema::table('shipping_parcels', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['commande_id']);

            // Make commande_id nullable
            $table->foreignUuid('commande_id')->nullable()->change();

            // Re-add the foreign key constraint but allow null
            $table->foreign('commande_id')->references('id')->on('commandes')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_parcels', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['commande_id']);

            // Make commande_id not nullable again
            $table->foreignUuid('commande_id')->nullable(false)->change();

            // Re-add the foreign key constraint
            $table->foreign('commande_id')->references('id')->on('commandes')->cascadeOnDelete();
        });
    }
};
