<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            // Add new user_id column
            $table->foreignUuid('user_id')->nullable()->after('affilie_id')->constrained('users')->restrictOnDelete();
            $table->index('user_id');
        });

        // Backfill user_id from profils_affilies.utilisateur_id
        DB::statement("
            UPDATE commandes
            SET user_id = (
                SELECT pa.utilisateur_id
                FROM profils_affilies pa
                WHERE pa.id = commandes.affilie_id
            )
            WHERE affilie_id IS NOT NULL
        ");

        // Make user_id required after backfill
        Schema::table('commandes', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
        });

        // Keep affilie_id temporarily for rollback safety
        // Will be removed in a future migration after verification
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
