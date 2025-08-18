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
        Schema::table('produits', function (Blueprint $table) {
            // Add rating fields after notes_admin
            $table->decimal('rating_value', 3, 2)->nullable()->after('notes_admin')->comment('Product rating from 0.00 to 5.00');
            $table->tinyInteger('rating_max')->default(5)->after('rating_value')->comment('Maximum rating value (for future flexibility)');
            $table->foreignUuid('rating_updated_by')->nullable()->after('rating_max')->constrained('users')->nullOnDelete()->comment('User who last updated the rating');
            $table->timestampTz('rating_updated_at')->nullable()->after('rating_updated_by')->comment('When the rating was last updated');

            // Add index for rating queries
            $table->index(['rating_value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            // Drop rating fields in reverse order
            $table->dropIndex(['rating_value']);
            $table->dropColumn(['rating_updated_at', 'rating_updated_by', 'rating_max', 'rating_value']);
        });
    }
};
