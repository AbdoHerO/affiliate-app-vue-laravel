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
            $table->boolean('sent_to_carrier')->default(true)->after('delivery_note_ref')
                ->comment('Whether order was sent to carrier (true) or is local/manual (false)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_parcels', function (Blueprint $table) {
            $table->dropColumn('sent_to_carrier');
        });
    }
};
