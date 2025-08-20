<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('variant_attributs', function (Blueprint $table) {
            $table->boolean('is_mandatory')->default(false)->after('actif');
            $table->integer('display_order')->default(0)->after('is_mandatory');
        });

        // Set Color and Size as mandatory
        DB::table('variant_attributs')
            ->whereIn('code', ['couleur', 'color', 'taille', 'size'])
            ->update([
                'is_mandatory' => true,
                'display_order' => DB::raw("CASE
                    WHEN code IN ('couleur', 'color') THEN 1
                    WHEN code IN ('taille', 'size') THEN 2
                    ELSE 0
                END")
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variant_attributs', function (Blueprint $table) {
            $table->dropColumn(['is_mandatory', 'display_order']);
        });
    }
};
