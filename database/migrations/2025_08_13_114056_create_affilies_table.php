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
        Schema::create('affilies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom_complet');
            $table->string('email')->unique();
            $table->string('telephone');
            $table->text('adresse');
            $table->string('ville');
            $table->string('pays');
            $table->string('mot_de_passe_hash');
            $table->timestamp('email_verified_at')->nullable();
            $table->enum('approval_status', ['pending_approval', 'approved', 'refused'])->default('pending_approval');
            $table->text('refusal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['email', 'approval_status']);
            $table->index('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affilies');
    }
};
