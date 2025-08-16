<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ShippingCity;

$city = ShippingCity::where('provider', 'ozonexpress')
    ->whereNotNull('city_id')
    ->where('city_id', '!=', '')
    ->where('active', true)
    ->first();

if (!$city) {
    echo "No active OzonExpress cities found!\n";
    $cities = ShippingCity::where('provider', 'ozonexpress')->take(5)->get();
    foreach ($cities as $c) {
        echo "- {$c->name} (City ID: '{$c->city_id}', Active: " . ($c->active ? 'Yes' : 'No') . ")\n";
    }
    exit(1);
}

echo "Valid OzonExpress city found:\n";
echo "City: {$city->name}\n";
echo "City ID: {$city->city_id}\n";
echo "ID: {$city->id}\n";
echo "Active: " . ($city->active ? 'Yes' : 'No') . "\n";
