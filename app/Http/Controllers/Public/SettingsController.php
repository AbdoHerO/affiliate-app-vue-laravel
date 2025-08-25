<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get public settings (for frontend)
     */
    public function getPublic(): JsonResponse
    {
        try {
            $settings = Setting::getPublic();
            $processedSettings = $this->processSettingsForResponse($settings);

            return response()->json([
                'success' => true,
                'data' => $processedSettings,
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
     * Check if a field is a file upload field
     */
    private function isFileUploadField(string $key): bool
    {
        $fileFields = [
            'app_logo',
            'app_favicon',
            'login_background_image',
            'signup_background_image'
        ];

        return in_array($key, $fileFields);
    }

    /**
     * Process settings for response (convert file paths to public URLs)
     */
    private function processSettingsForResponse(array $settings): array
    {
        $processed = [];

        foreach ($settings as $key => $value) {
            if ($this->isFileUploadField($key) && $value && !filter_var($value, FILTER_VALIDATE_URL)) {
                // If it's a file field and not already a URL, convert to public URL
                $processed[$key] = Storage::disk('public')->url($value);
            } else {
                $processed[$key] = $value;
            }
        }

        return $processed;
    }

    /**
     * Get app configuration for frontend
     */
    public function getAppConfig(): JsonResponse
    {
        try {
            // Try to get general settings, but handle if none exist
            $generalSettings = [];
            try {
                $generalSettings = Setting::getByCategory('general');
            } catch (\Exception $e) {
                Log::warning('No general settings found, using defaults: ' . $e->getMessage());
            }
            
            // Filter only the settings needed for app configuration with fallbacks
            $appConfig = [
                'app_name' => $generalSettings['app_name'] ?? 'Affiliate Platform',
                'app_description' => $generalSettings['app_description'] ?? 'Advanced Affiliate Marketing Platform',
                'app_logo' => $generalSettings['app_logo'] ?? '',
                'app_favicon' => $generalSettings['app_favicon'] ?? '',
                'primary_color' => $generalSettings['primary_color'] ?? '#6366F1',
                'secondary_color' => $generalSettings['secondary_color'] ?? '#8B5CF6',
                'app_theme' => $generalSettings['app_theme'] ?? 'light',
                'default_language' => $generalSettings['default_language'] ?? 'fr',
                'timezone' => $generalSettings['timezone'] ?? 'Africa/Casablanca',
                'currency' => $generalSettings['currency'] ?? 'MAD',
                'currency_symbol' => $generalSettings['currency_symbol'] ?? 'MAD',
                'date_format' => $generalSettings['date_format'] ?? 'DD/MM/YYYY',
                'maintenance_mode' => $generalSettings['maintenance_mode'] ?? false,
                'registration_enabled' => $generalSettings['registration_enabled'] ?? true,
                'login_background_image' => $generalSettings['login_background_image'] ?? '',
                'signup_background_image' => $generalSettings['signup_background_image'] ?? '',
            ];

            // Process file fields to convert to public URLs
            $processedConfig = $this->processSettingsForResponse($appConfig);

            return response()->json([
                'success' => true,
                'data' => $processedConfig,
                'message' => 'App configuration retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve app configuration: ' . $e->getMessage());
            
            // Return default configuration in case of any error
            $defaultConfig = [
                'app_name' => 'Affiliate Platform',
                'app_description' => 'Advanced Affiliate Marketing Platform',
                'app_logo' => '',
                'app_favicon' => '',
                'primary_color' => '#6366F1',
                'secondary_color' => '#8B5CF6',
                'app_theme' => 'light',
                'default_language' => 'fr',
                'timezone' => 'Africa/Casablanca',
                'currency' => 'MAD',
                'currency_symbol' => 'MAD',
                'date_format' => 'DD/MM/YYYY',
                'maintenance_mode' => false,
                'registration_enabled' => true,
                'login_background_image' => '',
                'signup_background_image' => '',
            ];
            
            return response()->json([
                'success' => true,
                'data' => $defaultConfig,
                'message' => 'App configuration retrieved with defaults (settings not found)'
            ]);
        }
    }
}
