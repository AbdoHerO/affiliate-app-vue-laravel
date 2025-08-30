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
            // First add the column if it doesn't exist
            $table->uuid('affiliate_parrained_by')->nullable()->after('bank_type');

            // Then add the foreign key constraint and index
            $table->foreign('affiliate_parrained_by')->references('id')->on('profils_affilies')->nullOnDelete();
            $table->index('affiliate_parrained_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['affiliate_parrained_by']);
            $table->dropIndex(['affiliate_parrained_by']);
            $table->dropColumn('affiliate_parrained_by');
        });
    }
};
