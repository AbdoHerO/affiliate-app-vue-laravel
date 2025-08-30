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
        // Update the comment to include the new status
        DB::statement("ALTER TABLE commandes MODIFY COLUMN statut VARCHAR(255) DEFAULT 'en_attente' COMMENT 'allowed: en_attente,confirmee,expediee,livree,annulee,retournee,returned_to_warehouse,echec_livraison'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert any orders with returned_to_warehouse status back to retournee
        DB::table('commandes')
            ->where('statut', 'returned_to_warehouse')
            ->update(['statut' => 'retournee']);
            
        // Revert the comment to the original
        DB::statement("ALTER TABLE commandes MODIFY COLUMN statut VARCHAR(255) DEFAULT 'en_attente' COMMENT 'allowed: en_attente,confirmee,expediee,livree,annulee,retournee,echec_livraison'");
    }
};
