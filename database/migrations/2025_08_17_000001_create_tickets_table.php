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
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject');
            $table->enum('status', [
                'open',
                'pending', 
                'waiting_user',
                'waiting_third_party',
                'resolved',
                'closed'
            ])->default('open');
            $table->enum('priority', [
                'low',
                'normal', 
                'high',
                'urgent'
            ])->default('normal');
            $table->enum('category', [
                'general',
                'orders',
                'payments',
                'commissions',
                'kyc',
                'technical',
                'other'
            ])->default('general');
            
            // Relationships
            $table->foreignUuid('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            
            // SLA tracking
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_activity_at');
            
            // Additional data
            $table->json('meta')->nullable();
            
            $table->timestampsTz();
            $table->softDeletesTz();
            
            // Indexes for performance
            $table->index('status');
            $table->index('priority');
            $table->index('category');
            $table->index('requester_id');
            $table->index('assignee_id');
            $table->index('last_activity_at');
            $table->index(['status', 'priority']);
            $table->index(['category', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
