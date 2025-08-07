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
        Schema::table('produit_propositions', function (Blueprint $table) {
            $table->string('titre')->nullable()->after('auteur_id');
            $table->text('image_url')->nullable()->after('description');
            $table->text('notes_admin')->nullable()->after('statut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produit_propositions', function (Blueprint $table) {
            $table->dropColumn(['titre', 'image_url', 'notes_admin']);
        });
    }
};
