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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            
            // Amount and method
            $table->decimal('amount', 12, 2);
            $table->enum('status', [
                'pending',
                'approved', 
                'in_payment',
                'paid',
                'rejected',
                'canceled'
            ])->default('pending');
            $table->enum('method', ['bank_transfer'])->default('bank_transfer');
            
            // Snapshot fields at request time
            $table->string('iban_rib', 34)->nullable()->comment('IBAN/RIB snapshot at withdrawal time');
            $table->string('bank_type', 100)->nullable()->comment('Bank type snapshot at withdrawal time');
            
            // Admin management
            $table->text('notes')->nullable()->comment('Admin notes');
            $table->text('admin_reason')->nullable()->comment('Reason for reject/cancel');
            
            // Payment tracking
            $table->string('payment_ref', 100)->nullable()->comment('Payment reference number');
            $table->string('evidence_path', 500)->nullable()->comment('Payment evidence file path');
            
            // Workflow timestamps
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            // Metadata for commission snapshots and additional info
            $table->json('meta')->nullable()->comment('Commission snapshots and additional metadata');
            
            $table->timestampsTz();
            $table->softDeletesTz();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('approved_at');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
