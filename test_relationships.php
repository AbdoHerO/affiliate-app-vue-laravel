<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AffiliateCartItem;
use App\Models\Produit;
use App\Models\User;

echo "🧪 Testing Cart Summary with Relationships\n";

try {
    // Get real data
    $product = Produit::where('actif', true)->first();
    $user = User::first();
    
    if (!$product || !$user) {
        echo "⚠️  Missing test data - skipping test\n";
        exit;
    }
    
    // Create a test cart item
    $cartItem = new \App\Models\AffiliateCartItem();
    $cartItem->user_id = $user->id;
    $cartItem->produit_id = $product->id;
    $cartItem->variante_id = null;
    $cartItem->qty = 2;
    $cartItem->added_at = now();
    $cartItem->save();
    
    echo "✅ Test cart item created\n";
    
    // Test the relationships
    $cartItems = \App\Models\AffiliateCartItem::with(['produit.images', 'produit.variantes', 'variante'])
        ->where('user_id', $user->id)
        ->get();
    
    echo "✅ Found " . $cartItems->count() . " cart items\n";
    
    foreach ($cartItems as $item) {
        echo "✅ Item: " . $item->produit->titre . " (Qty: " . $item->qty . ")\n";
        echo "   - Product price: " . $item->produit->prix_vente . "\n";
        echo "   - Item key: " . $item->item_key . "\n";
    }
    
    // Clean up
    $cartItem->delete();
    echo "✅ Test cart item cleaned up\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n🎉 Relationship test completed!\n";
