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
        Schema::create('shipping_parcels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->string('provider')->default('ozonexpress');
            $table->string('tracking_number')->nullable();
            $table->string('status')->nullable();
            $table->string('city_id')->nullable();
            $table->string('city_name')->nullable();
            $table->string('receiver')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->decimal('delivered_price', 12, 2)->nullable();
            $table->decimal('returned_price', 12, 2)->nullable();
            $table->decimal('refused_price', 12, 2)->nullable();
            $table->string('delivery_note_ref')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->string('last_status_text')->nullable();
            $table->string('last_status_code')->nullable();
            $table->timestampTz('last_status_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'tracking_number']);
            $table->index(['commande_id']);
            $table->index(['provider', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_parcels');
    }
};
