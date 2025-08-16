<?php

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppSettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only load settings after the application has booted
        // and database connections are available
        $this->app->booted(function () {
            $this->loadAppSettings();
        });
    }

    /**
     * Load app settings into Laravel config
     */
    protected function loadAppSettings(): void
    {
        try {
            // Check if the app_settings table exists
            if (!Schema::hasTable('app_settings')) {
                return;
            }

            // Load OzonExpress settings
            $this->loadOzonExpressSettings();

            // Add other setting groups here as needed
            // $this->loadEmailSettings();
            // $this->loadPaymentSettings();

        } catch (\Exception $e) {
            // Silently fail during migrations or when database is not available
            // Log the error in production
            if (app()->environment('production')) {
                logger()->warning('Failed to load app settings: ' . $e->getMessage());
            }
        }
    }

    /**
     * Load OzonExpress settings into config
     */
    protected function loadOzonExpressSettings(): void
    {
        $cacheKey = 'app_settings_ozonexpress';
        
        $settings = Cache::remember($cacheKey, 3600, function () {
            return [
                'customer_id' => AppSetting::get('ozonexpress.customer_id'),
                'api_key' => AppSetting::get('ozonexpress.api_key'),
            ];
        });

        // Merge with existing config
        Config::set('services.ozonexpress.customer_id', $settings['customer_id']);
        Config::set('services.ozonexpress.api_key', $settings['api_key']);

        // Also set the legacy config keys for backward compatibility
        Config::set('services.ozonexpress.id', $settings['customer_id']);
        Config::set('services.ozonexpress.key', $settings['api_key']);
    }

    /**
     * Clear settings cache
     */
    public static function clearCache(): void
    {
        Cache::forget('app_settings_ozonexpress');
        
        // Clear individual setting caches
        Cache::forget('app_setting_ozonexpress.customer_id');
        Cache::forget('app_setting_ozonexpress.api_key');
    }

    /**
     * Reload settings into config
     */
    public static function reload(): void
    {
        static::clearCache();
        
        // Reload settings
        $provider = app(static::class);
        $provider->loadAppSettings();
    }
}
