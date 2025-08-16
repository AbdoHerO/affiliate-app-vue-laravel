<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\Produit;
use App\Models\Adresse;
use App\Models\Boutique;
use App\Models\User;
use App\Services\OzonExpressService;
use Illuminate\Support\Facades\DB;

echo "=== Creating Test Order for OzonExpress ===\n\n";

try {
    DB::beginTransaction();

    // Get required data
    $boutique = Boutique::first();
    $affiliate = User::role('affiliate')->first();
    $affiliateProfile = \App\Models\ProfilAffilie::first();
    $product = Produit::where('actif', true)->first();
    $city = \App\Models\ShippingCity::where('provider', 'ozonexpress')
        ->whereNotNull('city_id')
        ->where('city_id', '!=', '')
        ->where('active', true)
        ->first();

    if (!$boutique || !$affiliate || !$affiliateProfile || !$product || !$city) {
        echo "Missing required data:\n";
        echo "- Boutique: " . ($boutique ? "✓" : "✗") . "\n";
        echo "- Affiliate: " . ($affiliate ? "✓" : "✗") . "\n";
        echo "- Affiliate Profile: " . ($affiliateProfile ? "✓" : "✗") . "\n";
        echo "- Product: " . ($product ? "✓" : "✗") . "\n";
        echo "- Valid City: " . ($city ? "✓ ({$city->name})" : "✗") . "\n";
        exit(1);
    }

    // Create or get client
    $client = Client::firstOrCreate(
        ['email' => 'test.ozon@example.com'],
        [
            'nom_complet' => 'Test Client OzonExpress',
            'telephone' => '0612345678',
        ]
    );

    // Create address
    $adresse = Adresse::create([
        'client_id' => $client->id,
        'adresse' => 'Test Address, ' . $city->name,
        'ville' => $city->name,
        'code_postal' => '20000',
        'pays' => 'Maroc',
        'is_default' => true,
    ]);

    // Create order
    $commande = new Commande();
    $commande->boutique_id = $boutique->id;
    $commande->user_id = $affiliate->id;
    $commande->affilie_id = $affiliateProfile->id;
    $commande->client_id = $client->id;
    $commande->adresse_id = $adresse->id;
    $commande->statut = 'confirmee';
    $commande->total_ht = 100.00;
    $commande->total_ttc = 120.00;
    $commande->mode_paiement = 'cod';
    $commande->notes = 'Test order for OzonExpress - ' . now()->format('Y-m-d H:i');
    $commande->save();

    echo "✓ Order created with ID: {$commande->id}\n";

    // Add order item
    CommandeArticle::create([
        'commande_id' => $commande->id,
        'produit_id' => $product->id,
        'quantite' => 1,
        'prix_unitaire' => 100.00,
        'total_ligne' => 100.00,
    ]);

    echo "✓ Order item added\n";

    // Send to OzonExpress
    echo "\nSending to OzonExpress...\n";
    $ozonService = app(OzonExpressService::class);
    $result = $ozonService->addParcel($commande);

    if ($result['success']) {
        echo "✅ SUCCESS! Order sent to OzonExpress\n";
        echo "   Tracking Number: {$result['data']->tracking_number}\n";
        echo "   Order ID: {$commande->id}\n";
        echo "   Client: {$client->nom_complet}\n";
        
        // Now track it
        echo "\nTracking the parcel...\n";
        $trackResult = $ozonService->track($result['data']->tracking_number);
        
        if ($trackResult['success']) {
            echo "✅ Tracking successful!\n";
            echo "   Status: {$trackResult['data']['parcel']->status}\n";
            echo "   Last Status: {$trackResult['data']['parcel']->last_status_text}\n";
        } else {
            echo "❌ Tracking failed: {$trackResult['message']}\n";
        }
        
    } else {
        echo "❌ FAILED to send to OzonExpress: {$result['message']}\n";
        if (isset($result['response'])) {
            echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n";
        }
    }

    DB::commit();

    echo "\n=== Test completed! ===\n";
    echo "You can now check the shipping orders page to see this order.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
