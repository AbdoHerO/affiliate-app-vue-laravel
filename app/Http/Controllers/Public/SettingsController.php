<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Get public settings (for frontend)
     */
    public function getPublic(): JsonResponse
    {
        try {
            $settings = Setting::getPublic();

            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Public settings retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve public settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve public settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get app configuration for frontend
     */
    public function getAppConfig(): JsonResponse
    {
        try {
            $generalSettings = Setting::getByCategory('general');
            
            // Filter only the settings needed for app configuration
            $appConfig = [
                'app_name' => $generalSettings['app_name'] ?? 'Affiliate Platform',
                'app_description' => $generalSettings['app_description'] ?? '',
                'app_logo' => $generalSettings['app_logo'] ?? '',
                'app_favicon' => $generalSettings['app_favicon'] ?? '',
                'primary_color' => $generalSettings['primary_color'] ?? '#6366F1',
                'secondary_color' => $generalSettings['secondary_color'] ?? '#8B5CF6',
                'app_theme' => $generalSettings['app_theme'] ?? 'light',
                'default_language' => $generalSettings['default_language'] ?? 'fr',
                'currency' => $generalSettings['currency'] ?? 'MAD',
                'currency_symbol' => $generalSettings['currency_symbol'] ?? 'MAD',
                'maintenance_mode' => $generalSettings['maintenance_mode'] ?? false,
                'registration_enabled' => $generalSettings['registration_enabled'] ?? true,
                'login_background_image' => $generalSettings['login_background_image'] ?? '',
                'signup_background_image' => $generalSettings['signup_background_image'] ?? '',
            ];

            return response()->json([
                'success' => true,
                'data' => $appConfig,
                'message' => 'App configuration retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve app configuration: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve app configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
