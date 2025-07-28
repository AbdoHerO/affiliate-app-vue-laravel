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
        Schema::create('expedition_evenements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('expedition_id')->constrained('expeditions')->cascadeOnDelete();
            $table->string('code')->comment('picked_up,out_for_delivery,delivered,failed');
            $table->text('message')->nullable();
            $table->timestampTz('occured_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expedition_evenements');
    }
};
