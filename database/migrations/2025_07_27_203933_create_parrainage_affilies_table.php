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
        Schema::create('parrainages_affilies', function (Blueprint $table) {
            $table->foreignUuid('parrain_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('filleul_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['parrain_id', 'filleul_id']);

            // Note: Self-sponsorship prevention will be handled at application level
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parrainages_affilies');
    }
};
