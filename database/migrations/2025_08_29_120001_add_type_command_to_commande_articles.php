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
        Schema::table('commande_articles', function (Blueprint $table) {
            // Add type_command field with enum values
            $table->string('type_command')
                ->default('order_sample')
                ->after('commission_rule_code')
                ->comment('allowed: order_sample,exchange');
            
            // Add index for performance
            $table->index('type_command');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commande_articles', function (Blueprint $table) {
            // Drop index first
            $table->dropIndex(['type_command']);
            
            // Then drop column
            $table->dropColumn('type_command');
        });
    }
};
