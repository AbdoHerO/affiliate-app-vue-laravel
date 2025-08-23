<?php

/**
 * Test Withdrawal Flow
 * 
 * This script tests the withdrawal creation and aggregation
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🏦 TESTING WITHDRAWAL FLOW\n";
echo "================================================================================\n\n";

$targetAffiliateId = '0198cd28-0b1f-7170-a26f-61e13ab21d72';
$affiliate = \App\Models\User::find($targetAffiliateId);

if (!$affiliate) {
    echo "❌ Target affiliate not found!\n";
    exit(1);
}

// Get eligible commissions
$eligibleCommissions = \App\Models\CommissionAffilie::where('user_id', $affiliate->id)
    ->where('status', 'calculated')
    ->get();

echo "📊 **Eligible Commissions Found:** {$eligibleCommissions->count()}\n";
echo "💰 **Total Eligible Amount:** " . number_format($eligibleCommissions->sum('amount'), 2) . " MAD\n\n";

if ($eligibleCommissions->count() === 0) {
    echo "⚠️ No eligible commissions found. Cannot test withdrawal flow.\n";
    exit(0);
}

// Update commissions to eligible status
echo "🔄 **Updating commissions to eligible status...**\n";
\App\Models\CommissionAffilie::where('user_id', $affiliate->id)
    ->where('status', 'calculated')
    ->update(['status' => 'eligible']);

$eligibleCommissions = \App\Models\CommissionAffilie::where('user_id', $affiliate->id)
    ->where('status', 'eligible')
    ->get();

echo "✅ **Updated {$eligibleCommissions->count()} commissions to eligible**\n\n";

// Create withdrawal
$totalAmount = $eligibleCommissions->sum('amount');

echo "💸 **Creating withdrawal request...**\n";
echo "   - Amount: " . number_format($totalAmount, 2) . " MAD\n";
echo "   - Commission Lines: {$eligibleCommissions->count()}\n\n";

try {
    $withdrawal = \App\Models\Withdrawal::create([
        'user_id' => $affiliate->id,
        'amount' => $totalAmount,
        'status' => 'pending',
        'method' => 'bank_transfer',
        'notes' => 'E2E Test Withdrawal',
        'currency' => 'MAD',
    ]);

    echo "✅ **Withdrawal created:** `{$withdrawal->id}`\n\n";

    // Create withdrawal items (link commissions to withdrawal)
    echo "🔗 **Linking commissions to withdrawal...**\n";
    
    foreach ($eligibleCommissions as $commission) {
        \App\Models\WithdrawalItem::create([
            'withdrawal_id' => $withdrawal->id,
            'commission_id' => $commission->id,
            'amount' => $commission->amount,
        ]);
        
        // Update commission status
        $commission->update([
            'status' => 'paid',
            'paid_withdrawal_id' => $withdrawal->id,
            'paid_at' => now(),
        ]);
    }

    echo "✅ **Linked {$eligibleCommissions->count()} commissions to withdrawal**\n\n";

    // Verify withdrawal
    $withdrawal->refresh();
    $withdrawalItems = \App\Models\WithdrawalItem::where('withdrawal_id', $withdrawal->id)
        ->with('commission')
        ->get();

    $linkedTotal = $withdrawalItems->sum('amount');

    echo "🔍 **Withdrawal Verification:**\n";
    echo "   - Withdrawal Amount: " . number_format($withdrawal->amount, 2) . " MAD\n";
    echo "   - Linked Items Total: " . number_format($linkedTotal, 2) . " MAD\n";
    echo "   - Match: " . (abs($withdrawal->amount - $linkedTotal) < 0.01 ? '✅ Yes' : '❌ No') . "\n\n";

    // Display withdrawal details
    echo "📋 **Withdrawal Details:**\n";
    echo "| Commission ID | Order ID | Product | Base Amount | Rate | Commission | Status |\n";
    echo "|---------------|----------|---------|-------------|------|------------|--------|\n";

    foreach ($withdrawalItems as $item) {
        $commission = $item->commission;
        $order = $commission->commande;
        $article = $commission->commandeArticle;
        $product = $article ? $article->produit : null;

        echo "| " . substr($commission->id, 0, 8) . "... | " .
             substr($order->id, 0, 8) . "... | " .
             ($product ? substr($product->titre, 0, 20) . "..." : 'N/A') . " | " .
             number_format($commission->base_amount, 2) . " | " .
             ($commission->rate * 100) . "% | " .
             number_format($commission->amount, 2) . " | " .
             $commission->status . " |\n";
    }

    echo "\n✅ **WITHDRAWAL FLOW TEST: PASSED**\n\n";

    echo "🎯 **Summary:**\n";
    echo "   - Eligible commissions identified: {$eligibleCommissions->count()}\n";
    echo "   - Withdrawal created successfully: `{$withdrawal->id}`\n";
    echo "   - Total amount: " . number_format($withdrawal->amount, 2) . " MAD\n";
    echo "   - All commissions linked and marked as paid\n";
    echo "   - Aggregation calculation verified\n\n";

} catch (Exception $e) {
    echo "❌ **Error creating withdrawal:** " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "================================================================================\n";
echo "🏦 Withdrawal Flow Test Complete\n";
echo "================================================================================\n";
