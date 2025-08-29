<?php

namespace App\Providers;

use App\Events\OrderDelivered;
use App\Listeners\CreateCommissionOnDelivery;
use App\Listeners\AwardReferralPointsOnDelivery;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderDelivered::class => [
            CreateCommissionOnDelivery::class,
            AwardReferralPointsOnDelivery::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
