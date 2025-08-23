<?php

/**
 * Check Affiliate Data Script
 * 
 * This script checks if the target affiliate exists and shows available data
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Checking Affiliate Data\n";
echo "================================================================================\n\n";

$targetAffiliateId = '0198cd28-0b1f-7170-a26f-61e13ab21d72';

// Check if target affiliate exists
$targetAffiliate = \App\Models\User::find($targetAffiliateId);

if ($targetAffiliate) {
    echo "✅ Target affiliate found!\n";
    echo "   ID: {$targetAffiliate->id}\n";
    echo "   Name: {$targetAffiliate->nom_complet}\n";
    echo "   Email: {$targetAffiliate->email}\n";
    echo "   Status: {$targetAffiliate->approval_status}\n\n";
} else {
    echo "❌ Target affiliate NOT found!\n\n";
    
    // Show available affiliates
    $affiliates = \App\Models\User::whereHas('roles', function($query) {
        $query->where('name', 'affiliate');
    })->take(5)->get();
    
    if ($affiliates->count() > 0) {
        echo "📋 Available affiliates (first 5):\n";
        foreach ($affiliates as $affiliate) {
            echo "   - {$affiliate->id} | {$affiliate->nom_complet} | {$affiliate->email}\n";
        }
        echo "\n";
    } else {
        echo "❌ No affiliates found in the database!\n\n";
    }
}

// Check orders
$orders = \App\Models\Commande::when($targetAffiliate, function($query) use ($targetAffiliate) {
    return $query->where('user_id', $targetAffiliate->id);
})->get();

echo "📦 Orders: {$orders->count()}\n";
if ($orders->count() > 0) {
    echo "   Statuses: " . $orders->pluck('statut')->unique()->implode(', ') . "\n";
    echo "   Total Amount: " . number_format($orders->sum('total_ttc'), 2) . " MAD\n";
}

// Check commissions
$commissions = \App\Models\CommissionAffilie::when($targetAffiliate, function($query) use ($targetAffiliate) {
    return $query->where('user_id', $targetAffiliate->id);
})->get();

echo "💰 Commissions: {$commissions->count()}\n";
if ($commissions->count() > 0) {
    echo "   Statuses: " . $commissions->pluck('status')->unique()->implode(', ') . "\n";
    echo "   Total Amount: " . number_format($commissions->sum('amount'), 2) . " MAD\n";
}

// Check withdrawals
$withdrawals = \App\Models\Withdrawal::when($targetAffiliate, function($query) use ($targetAffiliate) {
    return $query->where('user_id', $targetAffiliate->id);
})->get();

echo "🏦 Withdrawals: {$withdrawals->count()}\n";
if ($withdrawals->count() > 0) {
    echo "   Statuses: " . $withdrawals->pluck('status')->unique()->implode(', ') . "\n";
    echo "   Total Amount: " . number_format($withdrawals->sum('amount'), 2) . " MAD\n";
}

echo "\n================================================================================\n";

if (!$targetAffiliate) {
    echo "💡 SOLUTION: Create the target affiliate or use an existing one\n";
    echo "   Option 1: Run AffiliateQADataSeeder to create test data\n";
    echo "   Option 2: Use one of the existing affiliate IDs shown above\n";
    echo "   Option 3: Create the affiliate manually in the database\n";
} else if ($orders->count() === 0) {
    echo "💡 SOLUTION: Create test orders for the affiliate\n";
    echo "   Run OrderSeeder or create orders manually\n";
} else {
    echo "🎉 Data looks good! The E2E tests should work with this affiliate.\n";
}

echo "================================================================================\n";
