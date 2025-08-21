<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$model = new App\Models\AffiliateCartItem();
echo "Fillable properties: " . json_encode($model->getFillable()) . "\n";
