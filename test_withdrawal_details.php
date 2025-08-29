<?php

require_once 'bootstrap/app.php';

use App\Models\Withdrawal;
use App\Http\Resources\Affiliate\WithdrawalResource;

// Get a withdrawal with commissions
$withdrawal = Withdrawal::with([
    'commissions.commande_article',
    'commissions.produit'
])->first();

if ($withdrawal) {
    echo "=== Testing Withdrawal Details Resource ===\n";
    echo "Withdrawal ID: " . $withdrawal->id . "\n";
    echo "Commission count: " . $withdrawal->commissions->count() . "\n\n";
    
    // Transform through resource
    $resource = new WithdrawalResource($withdrawal);
    $data = $resource->toArray(request());
    
    echo "=== Resource Data Structure ===\n";
    if (isset($data['commissions']) && is_array($data['commissions'])) {
        foreach ($data['commissions'] as $index => $commission) {
            echo "Commission " . ($index + 1) . ":\n";
            echo "  - ID: " . ($commission['id'] ?? 'N/A') . "\n";
            echo "  - Type: " . ($commission['type'] ?? 'N/A') . "\n";
            
            if (isset($commission['commande_article'])) {
                echo "  - Order Article Data:\n";
                echo "    - ID: " . ($commission['commande_article']['id'] ?? 'N/A') . "\n";
                echo "    - Type Command: " . ($commission['commande_article']['type_command'] ?? 'N/A') . "\n";
            } else {
                echo "  - Order Article: NOT INCLUDED\n";
            }
            
            if (isset($commission['produit'])) {
                echo "  - Product Data:\n";
                echo "    - SKU: " . ($commission['produit']['sku'] ?? 'No SKU') . "\n";
                echo "    - Title: " . ($commission['produit']['titre'] ?? 'No Title') . "\n";
            } else {
                echo "  - Product: NOT INCLUDED\n";
            }
            echo "\n";
        }
    } else {
        echo "No commissions found in resource\n";
    }
} else {
    echo "No withdrawals found\n";
}

echo "\n=== Testing Complete ===\n";
