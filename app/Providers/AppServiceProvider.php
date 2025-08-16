<?php

namespace App\Providers;

use App\Services\OzonSettingsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register OzonSettingsService
        $this->app->singleton(OzonSettingsService::class, function () {
            return new OzonSettingsService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
