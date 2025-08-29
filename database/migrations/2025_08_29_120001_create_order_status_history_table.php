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
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('commandes')->cascadeOnDelete();
            $table->string('from_status')->nullable()->comment('Previous status, null for initial status');
            $table->string('to_status')->comment('New status after change');
            $table->enum('source', ['admin', 'affiliate', 'ozon_express', 'system'])
                  ->comment('Source of the status change');
            $table->text('note')->nullable()->comment('Optional note about the status change');
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('User who made the change, null for system/webhook changes');
            $table->json('meta')->nullable()->comment('Additional metadata about the change');
            $table->timestampTz('created_at')->useCurrent();

            // Indexes for performance
            $table->index(['order_id', 'created_at']);
            $table->index('source');
            $table->index('to_status');
            $table->index('changed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
