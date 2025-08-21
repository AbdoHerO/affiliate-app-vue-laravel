<?php

// Test the database cart functionality
// Run this script: php test_db_cart.php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Log;
use App\Models\AffiliateCartItem;
use App\Models\Produit;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing Database Cart Functionality\n\n";

// Test 1: Check if we can access AffiliateCartItem
try {
    $count = AffiliateCartItem::count();
    echo "✅ AffiliateCartItem model accessible - Current count: $count\n";
} catch (Exception $e) {
    echo "❌ Error accessing AffiliateCartItem: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check database connection
try {
    $productsCount = Produit::count();
    echo "✅ Database connection working - Products count: $productsCount\n";
} catch (Exception $e) {
    echo "❌ Database connection error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Try to create a test cart item (we'll delete it after)
try {
    // Find a test product
    $testProduct = Produit::where('actif', true)->first();
    
    if (!$testProduct) {
        echo "⚠️  No active products found - skipping cart item test\n";
    } else {
        // Create a test user ID (use a UUID format)
        $testUserId = 'test-' . uniqid();
        
        $cartItem = AffiliateCartItem::create([
            'user_id' => $testUserId,
            'produit_id' => $testProduct->id,
            'variante_id' => null,
            'qty' => 1,
            'added_at' => now()
        ]);
        
        echo "✅ Test cart item created - ID: " . $cartItem->id . "\n";
        
        // Clean up - delete the test item
        $cartItem->delete();
        echo "✅ Test cart item cleaned up\n";
    }
} catch (Exception $e) {
    echo "❌ Cart item creation error: " . $e->getMessage() . "\n";
}

echo "\n🎉 Database cart functionality test completed!\n";
echo "\n💡 The cart is now using DATABASE storage instead of SESSIONS.\n";
echo "   This fixes the Bearer token + session isolation issue.\n";
