<?php

/**
 * Test Corrected Commission Service
 * 
 * This script tests the corrected commission calculation logic
 * and compares it with the current implementation.
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 TESTING CORRECTED COMMISSION SERVICE\n";
echo "================================================================================\n\n";

$targetAffiliateId = '0198cd28-0b1f-7170-a26f-61e13ab21d72';
$affiliate = \App\Models\User::find($targetAffiliateId);

if (!$affiliate) {
    echo "❌ Target affiliate not found!\n";
    exit(1);
}

// Get existing orders and commissions
$orders = \App\Models\Commande::where('user_id', $affiliate->id)
    ->where('statut', 'livree')
    ->with(['articles.produit'])
    ->get();

$existingCommissions = \App\Models\CommissionAffilie::where('user_id', $affiliate->id)->get();

echo "📊 **Found {$orders->count()} delivered orders with {$existingCommissions->count()} existing commissions**\n\n";

// Initialize corrected commission service
$correctedService = new \App\Services\CorrectedCommissionService();

echo "## COMMISSION CALCULATION COMPARISON\n";
echo "================================================================================\n\n";

$totalCurrentCommissions = 0;
$totalCorrectedCommissions = 0;
$validationErrors = 0;

foreach ($orders as $orderIndex => $order) {
    if ($order->articles->count() === 0) continue;
    
    $orderNumber = $orderIndex + 1;
    echo "### Order O{$orderNumber} — Validation\n";
    echo "**Order ID:** `{$order->id}`\n\n";

    echo "| Line | Product | Cost | Recommended | Sale | Qty | Current Commission | Corrected Commission | Rule | Status |\n";
    echo "|------|---------|------|-------------|------|-----|-------------------|---------------------|------|--------|\n";

    foreach ($order->articles as $lineIndex => $article) {
        $lineNumber = $lineIndex + 1;
        $product = $article->produit;
        
        if (!$product) {
            echo "| {$lineNumber} | **Product Missing** | N/A | N/A | N/A | N/A | N/A | N/A | ERROR | ❌ |\n";
            $validationErrors++;
            continue;
        }

        // Get current commission
        $currentCommission = $existingCommissions->where('commande_article_id', $article->id)->first();
        $currentAmount = $currentCommission ? $currentCommission->amount : 0;

        // Calculate corrected commission
        $correctedData = $correctedService->calculateCommissionAmountCorrected($article, $product, $affiliate);
        $correctedAmount = $correctedData['amount'];
        $rule = $correctedData['rule_code'];

        $totalCurrentCommissions += $currentAmount;
        $totalCorrectedCommissions += $correctedAmount;

        $isCorrect = abs($currentAmount - $correctedAmount) < 0.01;
        $status = $isCorrect ? '✅' : '❌';
        
        if (!$isCorrect) {
            $validationErrors++;
        }

        echo "| {$lineNumber} | {$product->titre} | " . 
             number_format($product->prix_achat, 2) . " | " . 
             number_format($product->prix_vente, 2) . " | " . 
             number_format($article->prix_unitaire, 2) . " | " . 
             $article->quantite . " | " . 
             number_format($currentAmount, 2) . " | " . 
             number_format($correctedAmount, 2) . " | " . 
             $rule . " | " . 
             $status . " |\n";

        // Detailed error reporting
        if (!$isCorrect) {
            echo "\n**❌ COMMISSION CALCULATION ERROR:**\n";
            echo "- Product: {$product->titre}\n";
            echo "- Cost Price: " . number_format($product->prix_achat, 2) . " MAD\n";
            echo "- Recommended Price: " . number_format($product->prix_vente, 2) . " MAD\n";
            echo "- Fixed Commission: " . ($product->prix_affilie ? number_format($product->prix_affilie, 2) . " MAD" : 'None') . "\n";
            echo "- Sale Price: " . number_format($article->prix_unitaire, 2) . " MAD\n";
            echo "- Quantity: {$article->quantite}\n";
            echo "- Current Commission: " . number_format($currentAmount, 2) . " MAD\n";
            echo "- Corrected Commission: " . number_format($correctedAmount, 2) . " MAD\n";
            echo "- Difference: " . number_format(abs($currentAmount - $correctedAmount), 2) . " MAD\n";
            echo "- Rule Applied: {$rule}\n";
            
            if (isset($correctedData['calculation_details'])) {
                $details = $correctedData['calculation_details'];
                echo "- Calculation: {$details['calculation']}\n";
            }
            echo "\n";
        }
    }
    
    echo "\n---\n\n";
}

echo "## PRICING MODEL VALIDATION RESULTS\n";
echo "================================================================================\n\n";

echo "| Metric | Current System | Corrected System | Difference |\n";
echo "|--------|----------------|------------------|------------|\n";
echo "| Total Commissions | " . number_format($totalCurrentCommissions, 2) . " MAD | " . 
     number_format($totalCorrectedCommissions, 2) . " MAD | " . 
     number_format(abs($totalCurrentCommissions - $totalCorrectedCommissions), 2) . " MAD |\n";
echo "| Validation Errors | {$validationErrors} | 0 | {$validationErrors} |\n\n";

$accuracyPercentage = $existingCommissions->count() > 0 ? 
    (($existingCommissions->count() - $validationErrors) / $existingCommissions->count()) * 100 : 0;

echo "📊 **Current System Accuracy:** " . number_format($accuracyPercentage, 1) . "%\n";
echo "💰 **Commission Difference:** " . number_format(abs($totalCurrentCommissions - $totalCorrectedCommissions), 2) . " MAD\n\n";

if ($validationErrors === 0) {
    echo "🎉 **VALIDATION RESULT: CURRENT SYSTEM IS CORRECT**\n\n";
    echo "✅ Current commission calculations align with the expected pricing model\n";
    echo "✅ No corrections needed\n\n";
} else {
    echo "⚠️ **VALIDATION RESULT: CORRECTIONS NEEDED**\n\n";
    echo "❌ {$validationErrors} commission calculation errors found\n";
    echo "💡 Current system uses percentage-based calculation on total line amount\n";
    echo "🎯 Expected system should use margin-based calculation (sale_price - cost_price)\n\n";
    
    echo "### 🔧 **Recommended Actions:**\n";
    echo "1. **Update CommissionService** to use margin-based calculation\n";
    echo "2. **Recalculate existing commissions** using corrected logic\n";
    echo "3. **Update commission rules** to reflect pricing model\n";
    echo "4. **Add validation tests** to prevent future regressions\n\n";
}

echo "## DETAILED PRICING SCENARIOS\n";
echo "================================================================================\n\n";

// Analyze pricing scenarios
$scenarios = [
    'fixed_commission' => ['count' => 0, 'current_total' => 0, 'corrected_total' => 0],
    'recommended_margin' => ['count' => 0, 'current_total' => 0, 'corrected_total' => 0],
    'modified_margin' => ['count' => 0, 'current_total' => 0, 'corrected_total' => 0],
];

foreach ($orders as $order) {
    foreach ($order->articles as $article) {
        $product = $article->produit;
        if (!$product) continue;

        $currentCommission = $existingCommissions->where('commande_article_id', $article->id)->first();
        $currentAmount = $currentCommission ? $currentCommission->amount : 0;

        $correctedData = $correctedService->calculateCommissionAmountCorrected($article, $product, $affiliate);
        $correctedAmount = $correctedData['amount'];
        $rule = $correctedData['rule_code'];

        $scenarioKey = strtolower($rule);
        if (isset($scenarios[$scenarioKey])) {
            $scenarios[$scenarioKey]['count']++;
            $scenarios[$scenarioKey]['current_total'] += $currentAmount;
            $scenarios[$scenarioKey]['corrected_total'] += $correctedAmount;
        }
    }
}

echo "| Scenario | Count | Current Total | Corrected Total | Difference |\n";
echo "|----------|-------|---------------|-----------------|------------|\n";

foreach ($scenarios as $scenario => $data) {
    $difference = abs($data['current_total'] - $data['corrected_total']);
    echo "| " . ucwords(str_replace('_', ' ', $scenario)) . " | {$data['count']} | " . 
         number_format($data['current_total'], 2) . " | " . 
         number_format($data['corrected_total'], 2) . " | " . 
         number_format($difference, 2) . " |\n";
}

echo "\n## CONCLUSION\n";
echo "================================================================================\n\n";

if ($validationErrors === 0) {
    echo "🎯 **The current commission system is working correctly** and aligns with the expected pricing model.\n\n";
    echo "The commission calculations properly implement:\n";
    echo "- Fixed commission amounts when specified\n";
    echo "- Margin-based calculations for recommended prices\n";
    echo "- Dynamic margin calculations for modified prices\n\n";
} else {
    echo "🚨 **The current commission system needs correction** to align with the expected pricing model.\n\n";
    echo "**Key Issues Found:**\n";
    echo "- Current system uses percentage on total line amount\n";
    echo "- Expected system should use margin-based calculation\n";
    echo "- Commission amounts are significantly different\n\n";
    
    echo "**Impact:**\n";
    echo "- Affiliates may be receiving incorrect commission amounts\n";
    echo "- Business profitability calculations may be inaccurate\n";
    echo "- Pricing strategy effectiveness cannot be properly measured\n\n";
}

echo "================================================================================\n";
echo "🧪 Commission Service Validation Complete - " . now()->format('Y-m-d H:i:s') . "\n";
echo "================================================================================\n";
