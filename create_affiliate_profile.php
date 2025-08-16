<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\ProfilAffilie;
use App\Models\GammeAffilie;

echo "Creating affiliate profile...\n";

try {
    $user = User::role('affiliate')->first();
    if (!$user) {
        echo "No affiliate user found!\n";
        exit(1);
    }

    // Check if gamme exists
    $gamme = GammeAffilie::first();
    if (!$gamme) {
        echo "No gamme found, creating default gamme...\n";
        $gamme = GammeAffilie::create([
            'code' => 'DEFAULT',
            'libelle' => 'Default Gamme',
            'actif' => true,
        ]);
    }

    // Create profile
    $profile = ProfilAffilie::create([
        'utilisateur_id' => $user->id,
        'gamme_id' => $gamme->id,
        'statut' => 'actif',
    ]);

    echo "✓ Affiliate profile created with ID: {$profile->id}\n";
    echo "✓ User ID: {$user->id}\n";
    echo "✓ Gamme ID: {$gamme->id}\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
