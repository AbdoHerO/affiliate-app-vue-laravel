<?php

namespace App\Providers;

use App\Models\Commande;
use App\Observers\CommandeObserver;
use App\Services\OzonSettingsService;
use App\Http\View\Composers\AppSettingsComposer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        // Register model observers
        Commande::observe(CommandeObserver::class);

        // Register view composers
        View::composer('application', AppSettingsComposer::class);
    }
}
