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
        Schema::create('boutiques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->foreignUuid('proprietaire_id')->constrained('users')->restrictOnDelete();
            $table->string('email_pro')->nullable();
            $table->text('adresse')->nullable();
            $table->string('statut')->default('actif')->comment('allowed: actif,suspendu,desactive');
            $table->decimal('commission_par_defaut', 6, 3)->default(0.000)->comment('0.000 = 0%');
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boutiques');
    }
};
