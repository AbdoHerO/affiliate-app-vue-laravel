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
        Schema::create('blacklist_adresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('adresse_hash')->unique()->comment('hash de l\'adresse normalisée');
            $table->text('motif')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklist_adresses');
    }
};
