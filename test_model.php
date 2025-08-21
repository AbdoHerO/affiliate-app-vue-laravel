<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AffiliateCartItem;

echo "🧪 Testing AffiliateCartItem Model\n";

try {
    // Get a real product and user for testing
    $product = \App\Models\Produit::where('actif', true)->first();
    $user = \App\Models\User::first();
    
    if (!$product) {
        echo "⚠️  No active products found - skipping test\n";
        exit;
    }
    
    if (!$user) {
        echo "⚠️  No users found - skipping test\n";
        exit;
    }
    
    // Test 1: Create using new + save (same as controller)
    $cartItem = new AffiliateCartItem();
    $cartItem->user_id = $user->id;
    $cartItem->produit_id = $product->id;
    $cartItem->variante_id = null;
    $cartItem->qty = 1;
    $cartItem->added_at = now();
    $cartItem->save();
    
    echo "✅ Cart item created successfully with ID: " . $cartItem->id . "\n";
    
    // Test 2: Retrieve the item
    $retrieved = AffiliateCartItem::find($cartItem->id);
    echo "✅ Cart item retrieved: User ID = " . $retrieved->user_id . "\n";
    
    // Clean up
    $cartItem->delete();
    echo "✅ Test cart item cleaned up\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🎉 Model test completed!\n";
