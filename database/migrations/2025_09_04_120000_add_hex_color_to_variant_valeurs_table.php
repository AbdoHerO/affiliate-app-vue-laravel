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
        Schema::table('variant_valeurs', function (Blueprint $table) {
            $table->string('hex_color', 7)->nullable()->after('ordre')->comment('Hex color code for color variants (e.g., #FF0000)');
            $table->index(['attribut_id', 'hex_color']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variant_valeurs', function (Blueprint $table) {
            $table->dropIndex(['attribut_id', 'hex_color']);
            $table->dropColumn('hex_color');
        });
    }
};
