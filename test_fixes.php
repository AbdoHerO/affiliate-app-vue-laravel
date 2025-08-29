<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test commission data
use App\Models\CommissionAffilie;
use App\Http\Resources\Admin\CommissionResource;

echo "=== Testing Commission Data After Fixes ===\n";

$commission = CommissionAffilie::with([
    'affiliate:id,nom_complet,email',
    'commande:id,statut,total_ttc,created_at',
    'commandeArticle:id,quantite,prix_unitaire,total_ligne,type_command',
    'commandeArticle.produit:id,titre,sku'
])->whereNotNull('commande_article_id')->first();

if ($commission) {
    echo "Commission ID: " . $commission->id . "\n";
    echo "Article ID: " . $commission->commande_article_id . "\n";
    
    if ($commission->commandeArticle) {
        echo "Article Type Command: " . ($commission->commandeArticle->type_command ?? 'NULL') . "\n";
        
        if ($commission->commandeArticle->produit) {
            echo "Product SKU: " . ($commission->commandeArticle->produit->sku ?? 'NULL') . "\n";
            echo "Product Title: " . ($commission->commandeArticle->produit->titre ?? 'NULL') . "\n";
        } else {
            echo "No product found\n";
        }
    } else {
        echo "No article found\n";
    }
    
    echo "\n=== API Resource Output ===\n";
    $resource = new CommissionResource($commission);
    $data = $resource->toArray(request());
    
    if (isset($data['commande_article'])) {
        echo "Article data found in resource:\n";
        echo "- Type Command: " . ($data['commande_article']['type_command'] ?? 'NULL') . "\n";
        if (isset($data['commande_article']['produit'])) {
            echo "- Product SKU: " . ($data['commande_article']['produit']['sku'] ?? 'NULL') . "\n";
            echo "- Product Title: " . ($data['commande_article']['produit']['titre'] ?? 'NULL') . "\n";
        } else {
            echo "- No product in resource\n";
        }
    } else {
        echo "No article data in resource\n";
    }
    
} else {
    echo "No commission with article found\n";
}

echo "\n=== Testing Withdrawal Data ===\n";

use App\Models\Withdrawal;
use App\Http\Resources\Admin\WithdrawalResource;

$withdrawal = Withdrawal::with([
    'user:id,nom_complet,email,telephone,rib,bank_type',
    'items.commission:id,amount,status,created_at,commande_id,commande_article_id',
    'items.commission.commande:id,statut,total_ttc,created_at',
    'items.commission.commandeArticle:id,commande_id,produit_id,quantite,prix_unitaire,total_ligne,type_command',
    'items.commission.commandeArticle.produit:id,titre,sku'
])->whereHas('items')->first();

if ($withdrawal) {
    echo "Withdrawal ID: " . $withdrawal->id . "\n";
    echo "Items count: " . $withdrawal->items->count() . "\n";
    
    $resource = new WithdrawalResource($withdrawal);
    $data = $resource->toArray(request());
    
    if (isset($data['items']) && count($data['items']) > 0) {
        $firstItem = $data['items'][0];
        if (isset($firstItem['commission']['commande_article'])) {
            echo "First item has commande_article data:\n";
            echo "- Type Command: " . ($firstItem['commission']['commande_article']['type_command'] ?? 'NULL') . "\n";
            if (isset($firstItem['commission']['commande_article']['produit'])) {
                echo "- Product SKU: " . ($firstItem['commission']['commande_article']['produit']['sku'] ?? 'NULL') . "\n";
                echo "- Product Title: " . ($firstItem['commission']['commande_article']['produit']['titre'] ?? 'NULL') . "\n";
            }
        } else {
            echo "No commande_article data in first item\n";
        }
    } else {
        echo "No items in withdrawal\n";
    }
} else {
    echo "No withdrawal with items found\n";
}

echo "\n=== Done ===\n";
