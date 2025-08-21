<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\Http;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get affiliate user and create token
$affiliate = User::where('email', 'affiliate@cod.test')->first();
if (!$affiliate) {
    echo "Affiliate user not found!\n";
    exit(1);
}

$token = $affiliate->createToken('test-api')->plainTextToken;

// Make API request
$response = Http::withHeaders([
    'Accept' => 'application/json',
    'Authorization' => 'Bearer ' . $token
])->get('http://localhost:8000/api/affiliate/catalogue');

if ($response->successful()) {
    $data = $response->json();
    echo "API Response:\n";
    echo json_encode($data, JSON_PRETTY_PRINT);
} else {
    echo "API Error: " . $response->status() . "\n";
    echo $response->body();
}
