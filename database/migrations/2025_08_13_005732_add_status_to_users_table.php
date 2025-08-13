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
        Schema::table('users', function (Blueprint $table) {
            // Add approval status for affiliate queue management
            $table->enum('approval_status', ['pending_approval', 'approved', 'refused'])
                  ->default('pending_approval')
                  ->after('statut');

            $table->text('refusal_reason')->nullable()->after('approval_status');
            $table->index('approval_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropColumn(['approval_status', 'refusal_reason']);
        });
    }
};
