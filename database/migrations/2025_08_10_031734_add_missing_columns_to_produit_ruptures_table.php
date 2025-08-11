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
        Schema::table('produit_ruptures', function (Blueprint $table) {
            // Add produit_id column first if it doesn't exist
            if (!Schema::hasColumn('produit_ruptures', 'produit_id')) {
                $table->foreignUuid('produit_id')->nullable()->after('variante_id')->constrained('produits')->cascadeOnDelete();
            }

            // Check if other columns exist before adding them
            if (!Schema::hasColumn('produit_ruptures', 'motif')) {
                $table->string('motif')->default('Stock shortage')->after('produit_id');
            }
            if (!Schema::hasColumn('produit_ruptures', 'started_at')) {
                $table->timestampTz('started_at')->default(now())->after('motif');
            }
            if (!Schema::hasColumn('produit_ruptures', 'expected_restock_at')) {
                $table->timestampTz('expected_restock_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('produit_ruptures', 'resolved_at')) {
                $table->timestampTz('resolved_at')->nullable()->after('expected_restock_at');
            }
            if (!Schema::hasColumn('produit_ruptures', 'updated_at')) {
                $table->timestampTz('updated_at')->nullable()->after('created_at');
            }
        });

        // Populate produit_id from variante relationship if it's null
        DB::statement('
            UPDATE produit_ruptures pr
            JOIN produit_variantes pv ON pr.variante_id = pv.id
            SET pr.produit_id = pv.produit_id
            WHERE pr.produit_id IS NULL
        ');

        Schema::table('produit_ruptures', function (Blueprint $table) {
            // Rename actif to active for consistency if column exists
            if (Schema::hasColumn('produit_ruptures', 'actif') && !Schema::hasColumn('produit_ruptures', 'active')) {
                $table->renameColumn('actif', 'active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produit_ruptures', function (Blueprint $table) {
            // Rename back to actif first
            if (Schema::hasColumn('produit_ruptures', 'active')) {
                $table->renameColumn('active', 'actif');
            }

            // Remove added columns
            if (Schema::hasColumn('produit_ruptures', 'produit_id')) {
                $table->dropForeign(['produit_id']);
                $table->dropColumn('produit_id');
            }

            $columnsToRemove = ['motif', 'started_at', 'expected_restock_at', 'resolved_at', 'updated_at'];
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('produit_ruptures', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
