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
        Schema::create('ticket_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('related_type'); // e.g., 'App\Models\Commande'
            $table->string('related_id'); // UUID or int depending on related model
            
            $table->timestampsTz();
            
            // Indexes for performance
            $table->index('ticket_id');
            $table->index(['related_type', 'related_id']);
            $table->unique(['ticket_id', 'related_type', 'related_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_relations');
    }
};
