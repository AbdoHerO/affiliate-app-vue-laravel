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
            $table->string('cin', 20)->nullable()->after('email_verifie');
            $table->string('rib', 34)->nullable()->after('cin');
            $table->string('bank_type', 50)->nullable()->after('rib');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cin', 'rib', 'bank_type']);
        });
    }
};
