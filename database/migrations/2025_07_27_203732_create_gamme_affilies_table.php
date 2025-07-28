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
        Schema::create('gammes_affilies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique()->comment('basic,silver,golden');
            $table->string('libelle');
            $table->boolean('actif')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gammes_affilies');
    }
};
