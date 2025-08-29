<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test commission data
use App\Models\CommissionAffilie;
use App\Http\Resources\Admin\CommissionResource;

echo "=== Testing Commission Data ===\n";

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

echo "\n=== Total commissions with articles ===\n";
echo "Total: " . CommissionAffilie::whereNotNull('commande_article_id')->count() . "\n";
