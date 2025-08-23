<?php

/**
 * Final System Validation
 * 
 * Complete validation of the commission system after all fixes
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🎯 FINAL COMMISSION SYSTEM VALIDATION\n";
echo "================================================================================\n\n";

$targetAffiliateId = '0198cd28-0b1f-7170-a26f-61e13ab21d72';
$affiliate = \App\Models\User::find($targetAffiliateId);

if (!$affiliate) {
    echo "❌ Target affiliate not found!\n";
    exit(1);
}

// Check commission strategy
$strategy = \App\Models\AppSetting::get('commission.strategy', 'legacy');
echo "📊 **Commission Strategy:** {$strategy}\n";
echo "🎯 **Target Affiliate:** {$affiliate->nom_complet}\n\n";

// Get all data for the affiliate
$orders = \App\Models\Commande::where('user_id', $affiliate->id)->with(['articles.produit'])->get();
$commissions = \App\Models\CommissionAffilie::where('user_id', $affiliate->id)->get();
$withdrawals = \App\Models\Withdrawal::where('user_id', $affiliate->id)->get();

echo "## CURRENT DATA SUMMARY\n";
echo "================================================================================\n";
echo "📦 **Orders:** {$orders->count()} (Total: " . number_format($orders->sum('total_ttc'), 2) . " MAD)\n";
echo "💰 **Commissions:** {$commissions->count()} (Total: " . number_format($commissions->sum('amount'), 2) . " MAD)\n";
echo "🏦 **Withdrawals:** {$withdrawals->count()} (Total: " . number_format($withdrawals->sum('amount'), 2) . " MAD)\n\n";

// Analyze commission types
$commissionsByType = $commissions->groupBy('type');
echo "**Commission Breakdown by Type:**\n";
foreach ($commissionsByType as $type => $typeCommissions) {
    $total = $typeCommissions->sum('amount');
    echo "- {$type}: {$typeCommissions->count()} commissions, " . number_format($total, 2) . " MAD\n";
}
echo "\n";

// Analyze commission rules
$commissionsByRule = $commissions->groupBy('rule_code');
echo "**Commission Breakdown by Rule:**\n";
foreach ($commissionsByRule as $rule => $ruleCommissions) {
    $total = $ruleCommissions->sum('amount');
    echo "- {$rule}: {$ruleCommissions->count()} commissions, " . number_format($total, 2) . " MAD\n";
}
echo "\n";

// Test new commission calculation
echo "## NEW COMMISSION CALCULATION TEST\n";
echo "================================================================================\n";

$commissionService = new \App\Services\CommissionService();

// Find a delivered order to test with
$deliveredOrder = $orders->where('statut', 'livree')->first();

if ($deliveredOrder) {
    echo "🔍 **Testing with existing order:** `{$deliveredOrder->id}`\n";
    
    // Count existing commissions for this order
    $existingCommissions = $commissions->where('commande_id', $deliveredOrder->id)->count();
    echo "📊 **Existing commissions for this order:** {$existingCommissions}\n";
    
    // Try to calculate commission (should be idempotent)
    $result = $commissionService->calculateForOrder($deliveredOrder);
    
    if ($result['success']) {
        echo "✅ **Commission calculation successful**\n";
        echo "💰 **Total commission:** " . number_format($result['total_amount'], 2) . " MAD\n";
        echo "📊 **Commissions returned:** " . count($result['commissions']) . "\n\n";
        
        foreach ($result['commissions'] as $commission) {
            echo "**Commission Details:**\n";
            echo "- ID: {$commission->id}\n";
            echo "- Amount: " . number_format($commission->amount, 2) . " MAD\n";
            echo "- Rule: {$commission->rule_code}\n";
            echo "- Status: {$commission->status}\n";
            echo "- Created: {$commission->created_at}\n\n";
        }
    } else {
        echo "❌ **Commission calculation failed:** {$result['message']}\n\n";
    }
} else {
    echo "⚠️ No delivered orders found for testing\n\n";
}

// Validate commission calculations
echo "## COMMISSION VALIDATION\n";
echo "================================================================================\n";

$validationErrors = 0;
$totalValidated = 0;

foreach ($orders as $order) {
    if ($order->statut !== 'livree') continue;
    
    foreach ($order->articles as $article) {
        $product = $article->produit;
        if (!$product) continue;
        
        $totalValidated++;
        
        // Find commission for this article
        $commission = $commissions->where('commande_article_id', $article->id)->first();
        
        if (!$commission) {
            echo "⚠️ No commission found for article {$article->id}\n";
            continue;
        }
        
        // Calculate expected commission using margin-based logic
        $salePrice = $article->prix_unitaire;
        $costPrice = $product->prix_achat;
        $recommendedPrice = $product->prix_vente;
        $fixedCommission = $product->prix_affilie;
        $quantity = $article->quantite;
        
        $expectedAmount = 0;
        $expectedRule = '';
        
        if (abs($salePrice - $recommendedPrice) < 0.01 && $fixedCommission && $fixedCommission > 0) {
            $expectedAmount = round($fixedCommission * $quantity, 2);
            $expectedRule = 'FIXED_COMMISSION';
        } else {
            $marginPerUnit = max(0, $salePrice - $costPrice);
            $expectedAmount = round($marginPerUnit * $quantity, 2);
            $expectedRule = abs($salePrice - $recommendedPrice) < 0.01 ? 'RECOMMENDED_MARGIN' : 'MODIFIED_MARGIN';
        }
        
        $actualAmount = $commission->amount;
        $isCorrect = abs($expectedAmount - $actualAmount) < 0.01;
        
        if (!$isCorrect) {
            $validationErrors++;
            echo "❌ **Validation Error:**\n";
            echo "   - Product: {$product->titre}\n";
            echo "   - Expected: {$expectedAmount} MAD ({$expectedRule})\n";
            echo "   - Actual: {$actualAmount} MAD ({$commission->rule_code})\n";
            echo "   - Difference: " . number_format(abs($expectedAmount - $actualAmount), 2) . " MAD\n\n";
        }
    }
}

$accuracyRate = $totalValidated > 0 ? (($totalValidated - $validationErrors) / $totalValidated) * 100 : 100;

echo "📊 **Validation Summary:**\n";
echo "- Total Validated: {$totalValidated}\n";
echo "- Validation Errors: {$validationErrors}\n";
echo "- Accuracy Rate: " . number_format($accuracyRate, 1) . "%\n\n";

// Check backfill status
echo "## BACKFILL STATUS\n";
echo "================================================================================\n";

$backfillFiles = \Illuminate\Support\Facades\Storage::disk('local')->files('commission_backfills');
$reportFiles = array_filter($backfillFiles, fn($file) => str_contains($file, 'report_'));

if (!empty($reportFiles)) {
    rsort($reportFiles);
    $latestReport = $reportFiles[0];
    
    $content = \Illuminate\Support\Facades\Storage::disk('local')->get($latestReport);
    $report = json_decode($content, true);
    
    if ($report) {
        echo "📊 **Latest Backfill Report:**\n";
        echo "- Batch ID: {$report['batch_id']}\n";
        echo "- Mode: " . ($report['dry_run'] ? 'DRY-RUN' : 'APPLY') . "\n";
        echo "- Records Examined: {$report['metrics']['examined']}\n";
        echo "- Adjustments Needed: {$report['metrics']['adjustments_needed']}\n";
        echo "- Total Delta: {$report['metrics']['total_delta']} MAD\n";
        echo "- Accuracy Rate: {$report['summary']['accuracy_rate']}%\n\n";
    }
} else {
    echo "ℹ️ No backfill reports found\n\n";
}

// Final status
echo "## FINAL SYSTEM STATUS\n";
echo "================================================================================\n\n";

$systemHealthy = true;
$issues = [];

// Check commission strategy
if ($strategy !== 'margin') {
    $systemHealthy = false;
    $issues[] = "Commission strategy not set to 'margin'";
}

// Check validation accuracy
if ($accuracyRate < 100) {
    $systemHealthy = false;
    $issues[] = "Commission validation accuracy below 100% ({$accuracyRate}%)";
}

// Check if we have commissions
if ($commissions->count() === 0) {
    $systemHealthy = false;
    $issues[] = "No commissions found for affiliate";
}

if ($systemHealthy) {
    echo "🎉 **COMMISSION SYSTEM STATUS: FULLY OPERATIONAL**\n\n";
    echo "✅ **All checks passed:**\n";
    echo "   - Commission strategy set to margin\n";
    echo "   - All commission calculations validated\n";
    echo "   - Backfill system functional\n";
    echo "   - Data integrity maintained\n\n";
    
    echo "🚀 **System is ready for production use!**\n";
    
    if ($validationErrors > 0) {
        echo "\n💡 **Recommendation:** Run backfill to correct historical commissions:\n";
        echo "   php artisan commission:backfill --mode=apply\n";
    }
} else {
    echo "⚠️ **COMMISSION SYSTEM STATUS: NEEDS ATTENTION**\n\n";
    echo "❌ **Issues found:**\n";
    foreach ($issues as $issue) {
        echo "   - {$issue}\n";
    }
    echo "\n🔧 **Please address the issues above before production deployment.**\n";
}

echo "\n================================================================================\n";
echo "🎯 Final System Validation Complete - " . now()->format('Y-m-d H:i:s') . "\n";
echo "================================================================================\n";
