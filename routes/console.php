<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule commission processing every hour
Schedule::command('commissions:process-eligible')->hourly();

// Schedule OzonExpress parcel tracking every 30 minutes during business hours
Schedule::command('ozonexpress:track-parcels')
    ->everyThirtyMinutes()
    ->between('8:00', '20:00')
    ->withoutOverlapping()
    ->runInBackground();
