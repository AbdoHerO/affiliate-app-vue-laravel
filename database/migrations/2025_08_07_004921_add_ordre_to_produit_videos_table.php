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
        Schema::table('produit_videos', function (Blueprint $table) {
            $table->integer('ordre')->default(0)->after('titre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produit_videos', function (Blueprint $table) {
            $table->dropColumn('ordre');
        });
    }
};
