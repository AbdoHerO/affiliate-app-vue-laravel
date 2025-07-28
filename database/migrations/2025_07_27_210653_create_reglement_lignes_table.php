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
        Schema::create('reglement_lignes', function (Blueprint $table) {
            $table->foreignUuid('reglement_id')->constrained('reglements_affilies')->cascadeOnDelete();
            $table->foreignUuid('commission_id')->constrained('commissions_affilies')->cascadeOnDelete();
            $table->decimal('montant', 12, 2);

            $table->primary(['reglement_id', 'commission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reglement_lignes');
    }
};
