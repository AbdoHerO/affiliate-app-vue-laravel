<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Get all system settings
     */
    public function index(): JsonResponse
    {
        try {
            $settings = [
                'commission' => $this->getCommissionSettings(),
                'ozonexpress' => $this->getOzonExpressSettings(),
                'system' => $this->getSystemSettings(),
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
    private function getCommissionSettings(): array
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
    private function getOzonExpressSettings(): array
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
    private function getSystemSettings(): array
    {
        return [
            'maintenance_mode' => AppSetting::get('system.maintenance_mode', false),
            'debug_mode' => AppSetting::get('system.debug_mode', false),
            'log_level' => AppSetting::get('system.log_level', 'info'),
            'cache_enabled' => AppSetting::get('system.cache_enabled', true),
            'queue_enabled' => AppSetting::get('system.queue_enabled', true),
        ];
    }
}
