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
        Schema::table('shipping_parcels', function (Blueprint $table) {
            // Add new tracking fields if they don't exist
            if (!Schema::hasColumn('shipping_parcels', 'last_status_text')) {
                $table->string('last_status_text')->nullable()->after('last_synced_at');
            }
            if (!Schema::hasColumn('shipping_parcels', 'last_status_code')) {
                $table->string('last_status_code')->nullable()->after('last_status_text');
            }
            if (!Schema::hasColumn('shipping_parcels', 'last_status_at')) {
                $table->timestampTz('last_status_at')->nullable()->after('last_status_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_parcels', function (Blueprint $table) {
            $table->dropColumn(['last_status_text', 'last_status_code', 'last_status_at']);
        });
    }
};
