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
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['public', 'internal'])->default('public');
            $table->longText('body');
            $table->integer('attachments_count')->default(0);
            
            $table->timestampsTz();
            
            // Indexes for performance
            $table->index('ticket_id');
            $table->index(['ticket_id', 'created_at']);
            $table->index('sender_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
