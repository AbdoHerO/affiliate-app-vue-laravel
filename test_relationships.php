<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Checking Database Relationships ===\n";

use App\Models\CommissionAffilie;
use App\Models\CommandeArticle;

// Check commission data
$commission = CommissionAffilie::whereNotNull('commande_article_id')->first();
if ($commission) {
    echo "Commission ID: " . $commission->id . "\n";
    echo "Article ID: " . $commission->commande_article_id . "\n";
    
    // Check if article exists
    $article = CommandeArticle::find($commission->commande_article_id);
    if ($article) {
        echo "Article found - Product ID: " . ($article->produit_id ?? 'NULL') . "\n";
        echo "Article type_command: " . ($article->type_command ?? 'NULL') . "\n";
        
        // Check if product exists
        if ($article->produit_id) {
            $product = \App\Models\Produit::find($article->produit_id);
            if ($product) {
                echo "Product found - SKU: " . ($product->sku ?? 'NULL') . "\n";
                echo "Product title: " . ($product->titre ?? 'NULL') . "\n";
            } else {
                echo "Product with ID {$article->produit_id} not found!\n";
            }
        } else {
            echo "Article has no product_id!\n";
        }
    } else {
        echo "Article with ID {$commission->commande_article_id} not found!\n";
    }
} else {
    echo "No commission found!\n";
}

echo "\n=== Testing Eager Loading ===\n";

$commissionWithEager = CommissionAffilie::with([
    'commandeArticle.produit'
])->whereNotNull('commande_article_id')->first();

if ($commissionWithEager && $commissionWithEager->commandeArticle) {
    echo "Eager loaded article found\n";
    if ($commissionWithEager->commandeArticle->produit) {
        echo "Eager loaded product found - SKU: " . ($commissionWithEager->commandeArticle->produit->sku ?? 'NULL') . "\n";
    } else {
        echo "No product in eager loaded article\n";
    }
} else {
    echo "No article in eager loaded commission\n";
}

echo "\n=== Done ===\n";
