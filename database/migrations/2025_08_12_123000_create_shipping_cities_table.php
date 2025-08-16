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
        Schema::create('shipping_cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->default('ozonexpress');
            $table->string('city_id');
            $table->string('ref')->nullable();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->json('prices')->nullable(); // delivery, return, refused prices
            $table->json('meta')->nullable(); // additional metadata
            $table->timestampsTz();

            $table->unique(['provider', 'city_id']);
            $table->index(['provider', 'name']);
            $table->index(['provider', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_cities');
    }
};
