<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get all system settings (legacy method)
     */
    public function index(): JsonResponse
    {
        try {
            // Check if we should use new settings system
            if (request()->query('new_system') === 'true') {
                return $this->getNewSettings();
            }

            // Legacy settings
            $settings = [
                'commission' => $this->getCommissionSettingsArray(),
                'ozonexpress' => $this->getOzonExpressSettingsArray(),
                'system' => $this->getSystemSettingsArray(),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to load system settings', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load system settings'
            ], 500);
        }
    }

    /**
     * Get all settings grouped by category (new system)
     */
    public function getNewSettings(): JsonResponse
    {
        try {
            $categories = ['general', 'business', 'shipping', 'users', 'products', 'communication', 'security', 'system'];
            $settings = [];

            foreach ($categories as $category) {
                $settings[$category] = Setting::getByCategory($category);
            }

            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Settings retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get settings by category
     */
    public function getByCategory(string $category): JsonResponse
    {
        try {
            $settings = Setting::getByCategory($category);

            // Clean up nested keys (remove category prefixes)
            $cleanedSettings = [];
            foreach ($settings as $key => $value) {
                // Remove all category prefixes to get the clean key
                $cleanKey = $key;

                // Remove repeated category prefixes (e.g., "general.general.general.app_name" -> "app_name")
                while (strpos($cleanKey, $category . '.') === 0) {
                    $cleanKey = substr($cleanKey, strlen($category) + 1);
                }

                // Only keep the setting if we don't already have a cleaner version
                if (!isset($cleanedSettings[$cleanKey])) {
                    $cleanedSettings[$cleanKey] = $value;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $cleanedSettings,
                'message' => "Settings for {$category} retrieved successfully"
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to retrieve {$category} settings: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => "Failed to retrieve {$category} settings",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update settings by category
     */
    public function updateByCategory(Request $request, string $category): JsonResponse
    {
        try {
            $data = $request->all();



            // Validate the category
            $validCategories = ['general', 'business', 'shipping', 'users', 'products', 'communication', 'security', 'system'];
            if (!in_array($category, $validCategories)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid category'
                ], 400);
            }

            // Get validation rules for this category
            $rules = $this->getNewValidationRules($category);

            if (!empty($rules)) {
                $validator = Validator::make($data, $rules);
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }
            }

            DB::beginTransaction();

            // Filter out duplicate keys to avoid processing both clean and prefixed versions
            $filteredData = [];
            $processedKeys = [];

            foreach ($data as $key => $value) {
                // Handle file uploads for specific settings
                if ($this->isFileUploadField($key) && $request->hasFile($key)) {
                    $value = $this->handleFileUpload($request->file($key), $key);
                }

                // Clean the key to avoid nested categories
                $cleanKey = $key;
                // Remove category prefix if it already exists
                if (strpos($cleanKey, $category . '.') === 0) {
                    $cleanKey = substr($cleanKey, strlen($category) + 1);
                }

                // Skip if we've already processed this clean key (avoid duplicates)
                if (in_array($cleanKey, $processedKeys)) {
                    continue;
                }

                $processedKeys[] = $cleanKey;
                $settingKey = "{$category}.{$cleanKey}";
                $settingData = $this->getSettingMetadata($category, $cleanKey);

                // Debug log
                Log::info("Setting update attempt", [
                    'category' => $category,
                    'key' => $key,
                    'clean_key' => $cleanKey,
                    'setting_key' => $settingKey,
                    'value' => $value,
                    'setting_data' => $settingData
                ]);

                Setting::set($settingKey, $value, array_merge([
                    'category' => $category,
                    'type' => $settingData['type'] ?? 'string',
                    'is_public' => $settingData['is_public'] ?? false,
                    'is_encrypted' => $settingData['is_encrypted'] ?? false,
                    'description' => $settingData['description'] ?? null,
                ], $settingData));
            }

            DB::commit();

            // Return updated settings with public URLs for files
            $updatedSettings = Setting::getByCategory($category);
            $processedSettings = $this->processSettingsForResponse($updatedSettings);

            return response()->json([
                'success' => true,
                'message' => ucfirst($category) . ' settings updated successfully',
                'data' => $processedSettings
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update {$category} settings: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => "Failed to update {$category} settings",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get commission settings
     */
    public function getCommissionSettings(): JsonResponse
    {
        try {
            $settings = [
                'strategy' => AppSetting::get('commission.strategy', 'legacy'),
                'trigger_status' => AppSetting::get('commission.trigger_status', 'livree'),
                'cooldown_days' => AppSetting::get('commission.cooldown_days', 7),
                'default_rate' => AppSetting::get('commission.default_rate', 15.0),
                'auto_approve' => AppSetting::get('commission.auto_approve', false),
                'min_payout_amount' => AppSetting::get('commission.min_payout_amount', 100.0),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load commission settings'
            ], 500);
        }
    }

    /**
     * Update commission settings
     */
    public function updateCommissionSettings(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'strategy' => 'required|in:legacy,margin',
                'trigger_status' => 'required|in:livree,confirmee,expediee',
                'cooldown_days' => 'required|integer|min:0|max:365',
                'default_rate' => 'required|numeric|min:0|max:100',
                'auto_approve' => 'required|boolean',
                'min_payout_amount' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $settings = $validator->validated();

            // Update each setting
            foreach ($settings as $key => $value) {
                AppSetting::set("commission.{$key}", $value);
            }

            Log::info('Commission settings updated', [
                'settings' => $settings,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Commission settings updated successfully',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update commission settings', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update commission settings'
            ], 500);
        }
    }

    /**
     * Get OzonExpress settings
     */
    public function getOzonExpressSettings(): JsonResponse
    {
        try {
            $settings = [
                'enabled' => AppSetting::get('ozonexpress.enabled', true),
                'auto_tracking' => AppSetting::get('ozonexpress.auto_tracking', true),
                'tracking_frequency' => AppSetting::get('ozonexpress.tracking_frequency', 30),
                'api_timeout' => AppSetting::get('ozonexpress.api_timeout', 30),
                'max_retries' => AppSetting::get('ozonexpress.max_retries', 3),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load OzonExpress settings'
            ], 500);
        }
    }

    /**
     * Update OzonExpress settings
     */
    public function updateOzonExpressSettings(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean',
                'auto_tracking' => 'required|boolean',
                'tracking_frequency' => 'required|integer|min:5|max:120',
                'api_timeout' => 'required|integer|min:10|max:120',
                'max_retries' => 'required|integer|min:1|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $settings = $validator->validated();

            // Update each setting
            foreach ($settings as $key => $value) {
                AppSetting::set("ozonexpress.{$key}", $value);
            }

            Log::info('OzonExpress settings updated', [
                'settings' => $settings,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OzonExpress settings updated successfully',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update OzonExpress settings', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update OzonExpress settings'
            ], 500);
        }
    }

    /**
     * Get system settings
     */
    public function getSystemSettings(): JsonResponse
    {
        try {
            $settings = [
                'maintenance_mode' => AppSetting::get('system.maintenance_mode', false),
                'debug_mode' => AppSetting::get('system.debug_mode', false),
                'log_level' => AppSetting::get('system.log_level', 'info'),
                'cache_enabled' => AppSetting::get('system.cache_enabled', true),
                'queue_enabled' => AppSetting::get('system.queue_enabled', true),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load system settings'
            ], 500);
        }
    }

    /**
     * Update system settings
     */
    public function updateSystemSettings(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'maintenance_mode' => 'required|boolean',
                'debug_mode' => 'required|boolean',
                'log_level' => 'required|in:debug,info,warning,error',
                'cache_enabled' => 'required|boolean',
                'queue_enabled' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $settings = $validator->validated();

            // Update each setting
            foreach ($settings as $key => $value) {
                AppSetting::set("system.{$key}", $value);
            }

            Log::info('System settings updated', [
                'settings' => $settings,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'System settings updated successfully',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update system settings', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update system settings'
            ], 500);
        }
    }

    /**
     * Reset settings to defaults
     */
    public function resetToDefaults(Request $request): JsonResponse
    {
        try {
            $category = $request->get('category', 'all');

            $defaults = [
                'commission' => [
                    'commission.strategy' => 'margin',
                    'commission.trigger_status' => 'livree',
                    'commission.cooldown_days' => 7,
                    'commission.default_rate' => 15.0,
                    'commission.auto_approve' => false,
                    'commission.min_payout_amount' => 100.0,
                ],
                'ozonexpress' => [
                    'ozonexpress.enabled' => true,
                    'ozonexpress.auto_tracking' => true,
                    'ozonexpress.tracking_frequency' => 30,
                    'ozonexpress.api_timeout' => 30,
                    'ozonexpress.max_retries' => 3,
                ],
                'system' => [
                    'system.maintenance_mode' => false,
                    'system.debug_mode' => false,
                    'system.log_level' => 'info',
                    'system.cache_enabled' => true,
                    'system.queue_enabled' => true,
                ],
            ];

            $settingsToReset = $category === 'all' ? 
                array_merge(...array_values($defaults)) : 
                ($defaults[$category] ?? []);

            foreach ($settingsToReset as $key => $value) {
                AppSetting::set($key, $value);
            }

            Log::info('Settings reset to defaults', [
                'category' => $category,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Settings reset to defaults for category: {$category}",
                'data' => $settingsToReset
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reset settings', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings'
            ], 500);
        }
    }

    /**
     * Get commission settings (private helper)
     */
    private function getCommissionSettingsArray(): array
    {
        return [
            'strategy' => AppSetting::get('commission.strategy', 'legacy'),
            'trigger_status' => AppSetting::get('commission.trigger_status', 'livree'),
            'cooldown_days' => AppSetting::get('commission.cooldown_days', 7),
            'default_rate' => AppSetting::get('commission.default_rate', 15.0),
            'auto_approve' => AppSetting::get('commission.auto_approve', false),
            'min_payout_amount' => AppSetting::get('commission.min_payout_amount', 100.0),
        ];
    }

    /**
     * Get OzonExpress settings (private helper)
     */
    private function getOzonExpressSettingsArray(): array
    {
        return [
            'enabled' => AppSetting::get('ozonexpress.enabled', true),
            'auto_tracking' => AppSetting::get('ozonexpress.auto_tracking', true),
            'tracking_frequency' => AppSetting::get('ozonexpress.tracking_frequency', 30),
            'api_timeout' => AppSetting::get('ozonexpress.api_timeout', 30),
            'max_retries' => AppSetting::get('ozonexpress.max_retries', 3),
        ];
    }

    /**
     * Get system settings (private helper)
     */
    private function getSystemSettingsArray(): array
    {
        return [
            'maintenance_mode' => AppSetting::get('system.maintenance_mode', false),
            'debug_mode' => AppSetting::get('system.debug_mode', false),
            'log_level' => AppSetting::get('system.log_level', 'info'),
            'cache_enabled' => AppSetting::get('system.cache_enabled', true),
            'queue_enabled' => AppSetting::get('system.queue_enabled', true),
        ];
    }

    /**
     * Get validation rules for new settings system
     */
    private function getNewValidationRules(string $category): array
    {
        return match ($category) {
            'general' => [
                'app_name' => 'nullable|string|max:255',
                'app_description' => 'nullable|string|max:1000',
                'app_tagline' => 'nullable|string|max:255',
                'app_keywords' => 'nullable|string|max:500',
                'company_name' => 'nullable|string|max:255',
                'company_email' => 'nullable|email|max:255',
                'company_phone' => 'nullable|string|max:50',
                'company_address' => 'nullable|string|max:500',
                'company_website' => 'nullable|url|max:255',
                'company_social_facebook' => 'nullable|url|max:255',
                'company_social_instagram' => 'nullable|url|max:255',
                'company_social_twitter' => 'nullable|url|max:255',
                'app_logo' => 'nullable|string|max:255',
                'app_favicon' => 'nullable|string|max:255',
                'primary_color' => 'nullable|string|max:7',
                'secondary_color' => 'nullable|string|max:7',
                'login_background_image' => 'nullable|string|max:255',
                'signup_background_image' => 'nullable|string|max:255',
                'app_theme' => 'nullable|string|in:light,dark,auto',
                'default_language' => 'nullable|string|in:fr,ar,en',
                'timezone' => 'nullable|string|max:100',
                'currency' => 'nullable|string|max:10',
                'currency_symbol' => 'nullable|string|max:10',
                'date_format' => 'nullable|string|max:50',
                'time_format' => 'nullable|string|in:12,24',
                'number_format' => 'nullable|string|in:european,american,arabic',
                'maintenance_mode' => 'nullable|boolean',
                'registration_enabled' => 'nullable|boolean',
                'email_verification_required' => 'nullable|boolean',
                'kyc_verification_required' => 'nullable|boolean',
                'max_file_upload_size' => 'nullable|integer|min:1|max:100',
                'allowed_file_types' => 'nullable|string|max:255',
                'session_timeout' => 'nullable|integer|min:5|max:1440',
                'password_min_length' => 'nullable|integer|min:6|max:50',
                'password_require_special' => 'nullable|boolean',
                'app_version' => 'nullable|string|max:50',
            ],
            'business' => [
                'default_commission_rate' => 'required|numeric|min:0|max:100',
                'min_commission_threshold' => 'required|numeric|min:0',
                'commission_calculation_method' => 'required|string|in:per_order,per_product,percentage',
                'tiered_commissions_enabled' => 'boolean',
                'tier_1_rate' => 'nullable|numeric|min:0|max:100',
                'tier_1_threshold' => 'nullable|numeric|min:0',
                'tier_2_rate' => 'nullable|numeric|min:0|max:100',
                'tier_2_threshold' => 'nullable|numeric|min:0',
                'tier_3_rate' => 'nullable|numeric|min:0|max:100',
                'tier_3_threshold' => 'nullable|numeric|min:0',
                'auto_confirm_timeout' => 'required|integer|min:1|max:168',
                'order_number_prefix' => 'required|string|max:10',
                'order_number_format' => 'required|string|max:50',
                'return_window_days' => 'required|integer|min:0|max:30',
                'refund_processing_days' => 'required|integer|min:1|max:14',
                'min_withdrawal_amount' => 'required|numeric|min:1',
                'max_withdrawal_amount' => 'required|numeric|min:1',
                'withdrawal_fee_percentage' => 'required|numeric|min:0|max:10',
                'withdrawal_fee_fixed' => 'required|numeric|min:0',
                'withdrawal_processing_days' => 'required|integer|min:1|max:14',
                'tax_rate' => 'required|numeric|min:0|max:50',
                'tax_included_in_prices' => 'boolean',
                'payment_delay_days' => 'required|integer|min:0|max:90',
                'auto_payout_enabled' => 'boolean',
                'auto_payout_threshold' => 'nullable|numeric|min:1',
            ],
            default => [],
        };
    }

    /**
     * Get setting metadata for proper storage
     */
    private function getSettingMetadata(string $category, string $key): array
    {
        $metadata = [
            'general' => [
                'app_name' => ['type' => 'string', 'is_public' => true, 'description' => 'Application name'],
                'app_description' => ['type' => 'string', 'is_public' => true, 'description' => 'Application description'],
                'app_tagline' => ['type' => 'string', 'is_public' => true, 'description' => 'Application tagline'],
                'app_keywords' => ['type' => 'string', 'is_public' => true, 'description' => 'Application keywords'],
                'company_name' => ['type' => 'string', 'is_public' => true, 'description' => 'Company name'],
                'company_email' => ['type' => 'string', 'is_public' => false, 'description' => 'Company email'],
                'company_phone' => ['type' => 'string', 'is_public' => true, 'description' => 'Company phone'],
                'company_address' => ['type' => 'string', 'is_public' => true, 'description' => 'Company address'],
                'company_website' => ['type' => 'string', 'is_public' => true, 'description' => 'Company website'],
                'company_social_facebook' => ['type' => 'string', 'is_public' => true, 'description' => 'Facebook URL'],
                'company_social_instagram' => ['type' => 'string', 'is_public' => true, 'description' => 'Instagram URL'],
                'company_social_twitter' => ['type' => 'string', 'is_public' => true, 'description' => 'Twitter URL'],
                'app_logo' => ['type' => 'string', 'is_public' => true, 'description' => 'Application logo URL'],
                'app_favicon' => ['type' => 'string', 'is_public' => true, 'description' => 'Application favicon URL'],
                'primary_color' => ['type' => 'string', 'is_public' => true, 'description' => 'Primary brand color'],
                'secondary_color' => ['type' => 'string', 'is_public' => true, 'description' => 'Secondary brand color'],
                'login_background_image' => ['type' => 'string', 'is_public' => true, 'description' => 'Login background image URL'],
                'signup_background_image' => ['type' => 'string', 'is_public' => true, 'description' => 'Signup background image URL'],
                'app_theme' => ['type' => 'string', 'is_public' => true, 'description' => 'Application theme'],
                'default_language' => ['type' => 'string', 'is_public' => true, 'description' => 'Default language'],
                'timezone' => ['type' => 'string', 'is_public' => false, 'description' => 'Default timezone'],
                'currency' => ['type' => 'string', 'is_public' => true, 'description' => 'Default currency'],
                'currency_symbol' => ['type' => 'string', 'is_public' => true, 'description' => 'Currency symbol'],
                'date_format' => ['type' => 'string', 'is_public' => true, 'description' => 'Date format'],
                'time_format' => ['type' => 'string', 'is_public' => true, 'description' => 'Time format'],
                'number_format' => ['type' => 'string', 'is_public' => true, 'description' => 'Number format'],
                'maintenance_mode' => ['type' => 'boolean', 'is_public' => true, 'description' => 'Maintenance mode'],
                'registration_enabled' => ['type' => 'boolean', 'is_public' => true, 'description' => 'Registration enabled'],
                'email_verification_required' => ['type' => 'boolean', 'is_public' => true, 'description' => 'Email verification required'],
                'kyc_verification_required' => ['type' => 'boolean', 'is_public' => false, 'description' => 'KYC verification required'],
                'max_file_upload_size' => ['type' => 'integer', 'is_public' => false, 'description' => 'Max file upload size (MB)'],
                'allowed_file_types' => ['type' => 'string', 'is_public' => false, 'description' => 'Allowed file types'],
                'session_timeout' => ['type' => 'integer', 'is_public' => false, 'description' => 'Session timeout (minutes)'],
                'password_min_length' => ['type' => 'integer', 'is_public' => false, 'description' => 'Minimum password length'],
                'password_require_special' => ['type' => 'boolean', 'is_public' => false, 'description' => 'Require special characters in password'],
                'app_version' => ['type' => 'string', 'is_public' => true, 'description' => 'Application version'],
            ],
            'business' => [
                'default_commission_rate' => ['type' => 'float', 'is_public' => false, 'description' => 'Default commission rate'],
                'min_commission_threshold' => ['type' => 'float', 'is_public' => false, 'description' => 'Minimum commission threshold'],
                'commission_calculation_method' => ['type' => 'string', 'is_public' => false, 'description' => 'Commission calculation method'],
                'tiered_commissions_enabled' => ['type' => 'boolean', 'is_public' => false, 'description' => 'Tiered commissions enabled'],
                'tier_1_rate' => ['type' => 'float', 'is_public' => false, 'description' => 'Tier 1 commission rate'],
                'tier_1_threshold' => ['type' => 'float', 'is_public' => false, 'description' => 'Tier 1 threshold'],
                'tier_2_rate' => ['type' => 'float', 'is_public' => false, 'description' => 'Tier 2 commission rate'],
                'tier_2_threshold' => ['type' => 'float', 'is_public' => false, 'description' => 'Tier 2 threshold'],
                'tier_3_rate' => ['type' => 'float', 'is_public' => false, 'description' => 'Tier 3 commission rate'],
                'tier_3_threshold' => ['type' => 'float', 'is_public' => false, 'description' => 'Tier 3 threshold'],
                'auto_confirm_timeout' => ['type' => 'integer', 'is_public' => false, 'description' => 'Auto-confirm timeout in hours'],
                'order_number_prefix' => ['type' => 'string', 'is_public' => false, 'description' => 'Order number prefix'],
                'order_number_format' => ['type' => 'string', 'is_public' => false, 'description' => 'Order number format'],
                'return_window_days' => ['type' => 'integer', 'is_public' => false, 'description' => 'Return window in days'],
                'refund_processing_days' => ['type' => 'integer', 'is_public' => false, 'description' => 'Refund processing days'],
                'min_withdrawal_amount' => ['type' => 'float', 'is_public' => false, 'description' => 'Minimum withdrawal amount'],
                'max_withdrawal_amount' => ['type' => 'float', 'is_public' => false, 'description' => 'Maximum withdrawal amount'],
                'withdrawal_fee_percentage' => ['type' => 'float', 'is_public' => false, 'description' => 'Withdrawal fee percentage'],
                'withdrawal_fee_fixed' => ['type' => 'float', 'is_public' => false, 'description' => 'Fixed withdrawal fee'],
                'withdrawal_processing_days' => ['type' => 'integer', 'is_public' => false, 'description' => 'Withdrawal processing days'],
                'tax_rate' => ['type' => 'float', 'is_public' => false, 'description' => 'Tax rate percentage'],
                'tax_included_in_prices' => ['type' => 'boolean', 'is_public' => false, 'description' => 'Tax included in prices'],
                'payment_delay_days' => ['type' => 'integer', 'is_public' => false, 'description' => 'Payment delay in days'],
                'auto_payout_enabled' => ['type' => 'boolean', 'is_public' => false, 'description' => 'Auto payout enabled'],
                'auto_payout_threshold' => ['type' => 'float', 'is_public' => false, 'description' => 'Auto payout threshold amount'],
            ],
        ];

        return $metadata[$category][$key] ?? ['type' => 'string', 'is_public' => false];
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
     * Handle file upload for settings
     */
    private function handleFileUpload($file, string $key): string
    {
        // Validate file
        $allowedMimes = $this->getAllowedMimesForField($key);
        $maxSize = $this->getMaxSizeForField($key);

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception("Invalid file type for {$key}");
        }

        if ($file->getSize() > $maxSize) {
            throw new \Exception("File too large for {$key}");
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = $key . '_' . time() . '.' . $extension;

        // Store file in public disk
        $path = $file->storeAs('settings', $filename, 'public');

        // Return public URL
        return Storage::disk('public')->url($path);
    }

    /**
     * Get allowed MIME types for a field
     */
    private function getAllowedMimesForField(string $key): array
    {
        $mimeMap = [
            'app_logo' => ['image/jpeg', 'image/png', 'image/svg+xml'],
            'app_favicon' => ['image/x-icon', 'image/png', 'image/jpeg'],
            'login_background_image' => ['image/jpeg', 'image/png', 'image/webp'],
            'signup_background_image' => ['image/jpeg', 'image/png', 'image/webp']
        ];

        return $mimeMap[$key] ?? ['image/jpeg', 'image/png'];
    }

    /**
     * Get max file size for a field (in bytes)
     */
    private function getMaxSizeForField(string $key): int
    {
        $sizeMap = [
            'app_logo' => 2 * 1024 * 1024, // 2MB
            'app_favicon' => 1 * 1024 * 1024, // 1MB
            'login_background_image' => 5 * 1024 * 1024, // 5MB
            'signup_background_image' => 5 * 1024 * 1024 // 5MB
        ];

        return $sizeMap[$key] ?? 2 * 1024 * 1024; // Default 2MB
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
}
