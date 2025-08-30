<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Facebook Pixel API key setting
        Setting::updateOrCreate(
            ['key' => 'general.facebook_pxm_api_key'],
            [
                'value' => '',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Facebook Pixel API Key for tracking',
                'is_public' => false,
                'sort_order' => 7,
            ]
        );

        // Add company_logo setting (separate from app_logo)
        Setting::updateOrCreate(
            ['key' => 'general.company_logo'],
            [
                'value' => '',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Company logo URL',
                'is_public' => true,
                'sort_order' => 5,
            ]
        );

        // Add favicon setting (separate from app_favicon)
        Setting::updateOrCreate(
            ['key' => 'general.favicon'],
            [
                'value' => '',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Application favicon URL',
                'is_public' => true,
                'sort_order' => 6,
            ]
        );

        // Add app_slogan setting
        Setting::updateOrCreate(
            ['key' => 'general.app_slogan'],
            [
                'value' => '',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Application slogan',
                'is_public' => true,
                'sort_order' => 3,
            ]
        );

        // Add app_keywords setting
        Setting::updateOrCreate(
            ['key' => 'general.app_keywords'],
            [
                'value' => 'affiliation, COD, ecommerce',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Application keywords for SEO',
                'is_public' => true,
                'sort_order' => 4,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the added settings
        Setting::where('key', 'general.facebook_pxm_api_key')->delete();
        Setting::where('key', 'general.company_logo')->delete();
        Setting::where('key', 'general.favicon')->delete();
        Setting::where('key', 'general.app_slogan')->delete();
        Setting::where('key', 'general.app_keywords')->delete();
    }
};
