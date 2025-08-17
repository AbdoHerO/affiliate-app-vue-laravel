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
        Schema::table('commissions_affilies', function (Blueprint $table) {
            // Add commande_id for order-level commissions (nullable for backward compatibility)
            $table->foreignUuid('commande_id')->nullable()->after('commande_article_id')->constrained('commandes')->cascadeOnDelete();

            // Commission calculation fields
            $table->decimal('base_amount', 12, 2)->nullable()->after('type')->comment('Base amount for calculation');
            $table->decimal('rate', 7, 4)->nullable()->after('base_amount')->comment('Commission rate (percentage or fixed)');
            $table->integer('qty')->nullable()->after('rate')->comment('Quantity for calculation');

            // Add new amount field for consistency
            $table->decimal('amount', 12, 2)->nullable()->after('qty')->comment('Final commission amount');
            $table->string('currency', 3)->default('MAD')->after('amount');

            // Make legacy montant field nullable
            $table->decimal('montant', 12, 2)->nullable()->change();
            
            // Enhanced status management
            $table->string('status')->after('currency')->default('pending_calc')->comment('pending_calc,calculated,eligible,approved,rejected,paid,adjusted,canceled');
            $table->string('rule_code', 64)->nullable()->after('status')->comment('Commission rule applied');
            $table->text('notes')->nullable()->after('rule_code')->comment('Admin notes');
            
            // Workflow timestamps
            $table->timestamp('eligible_at')->nullable()->after('notes');
            $table->timestamp('approved_at')->nullable()->after('eligible_at');
            $table->timestamp('paid_at')->nullable()->after('approved_at');
            
            // Payment tracking
            $table->uuid('paid_withdrawal_id')->nullable()->after('paid_at')->comment('Reference to withdrawal/payment');
            
            // Metadata for adjustments and history
            $table->json('meta')->nullable()->after('paid_withdrawal_id')->comment('Additional metadata');
            
            // Add soft deletes
            $table->softDeletesTz();
            
            // Add indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['commande_id', 'status']);
            $table->index(['status', 'eligible_at']);
            $table->index('approved_at');
            $table->index('paid_at');
        });
        
        // Update existing records to use new status values
        DB::statement("UPDATE commissions_affilies SET status = 
            CASE 
                WHEN statut = 'en_attente' THEN 'calculated'
                WHEN statut = 'valide' THEN 'eligible' 
                WHEN statut = 'paye' THEN 'paid'
                WHEN statut = 'annule' THEN 'canceled'
                ELSE 'calculated'
            END");
            
        // Copy montant to amount
        DB::statement("UPDATE commissions_affilies SET amount = montant WHERE amount IS NULL");
        
        // Set base_amount from amount for existing records
        DB::statement("UPDATE commissions_affilies SET base_amount = amount WHERE base_amount IS NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_affilies', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['commande_id', 'status']);
            $table->dropIndex(['status', 'eligible_at']);
            $table->dropIndex('approved_at');
            $table->dropIndex('paid_at');

            // Drop foreign key constraint
            $table->dropForeign(['commande_id']);

            // Drop soft deletes
            $table->dropSoftDeletesTz();

            // Drop columns
            $table->dropColumn([
                'commande_id',
                'base_amount',
                'rate',
                'qty',
                'amount',
                'currency',
                'status',
                'rule_code',
                'notes',
                'eligible_at',
                'approved_at',
                'paid_at',
                'paid_withdrawal_id',
                'meta'
            ]);

            // Restore montant field to NOT NULL
            $table->decimal('montant', 12, 2)->change();
        });
    }
};
