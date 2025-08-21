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
        Schema::create('affiliate_cart_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->index(); // Affiliate user ID
            $table->uuid('produit_id');
            $table->uuid('variante_id')->nullable();
            $table->integer('qty');
            $table->timestamp('added_at');
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('produit_id')->references('id')->on('produits')->onDelete('cascade');
            $table->foreign('variante_id')->references('id')->on('produit_variantes')->onDelete('cascade');

            // Unique constraint to prevent duplicate items
            $table->unique(['user_id', 'produit_id', 'variante_id'], 'unique_cart_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_cart_items');
    }
};
