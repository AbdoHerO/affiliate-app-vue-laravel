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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json, array
            $table->string('category')->default('general')->index(); // general, business, shipping, etc.
            $table->string('group')->nullable()->index(); // sub-grouping within category
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // can be accessed by frontend
            $table->boolean('is_encrypted')->default(false); // sensitive data like API keys
            $table->json('validation_rules')->nullable(); // Laravel validation rules
            $table->json('options')->nullable(); // for select/radio options
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
