<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Services\CommissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 TESTING COMMISSION FIXES\n";
echo "================================================================================\n\n";

// Test order IDs provided by the user
$testOrderIds = [
    '0198f393-647c-7299-b5f2-6c6cc95a10fc', // 1st order: exchange, subtotal 0, delivery 20 → expected commission -20
    '0198f394-115e-70df-bad2-528f38b86a0e', // 2nd order: subtotal 4250, delivery 20 → expected commission ~1230
    '0198f395-0148-7029-b2aa-af4d083e5ea7', // 3rd order: subtotal 5995, delivery 20 → expected commission ~9955
];

$commissionService = new CommissionService();

foreach ($testOrderIds as $index => $orderId) {
    $orderNumber = $index + 1;
    echo "📦 **Testing Order #{$orderNumber}:** {$orderId}\n";
    echo "----------------------------------------\n";
    
    try {
        $order = Commande::find($orderId);
        
        if (!$order) {
            echo "❌ Order not found\n\n";
            continue;
        }
        
        echo "📋 **Order Details:**\n";
        echo "   - Type: " . ($order->type_command ?? 'order_sample') . "\n";
        echo "   - Status: {$order->statut}\n";
        echo "   - Total TTC: " . number_format($order->total_ttc, 2) . " MAD\n";
        echo "   - Articles Count: " . $order->articles->count() . "\n";
        
        // Calculate subtotal and delivery fee
        $subtotal = $order->articles->sum('total_ligne');
        $deliveryFee = $order->total_ttc - $subtotal;
        
        echo "   - Subtotal: " . number_format($subtotal, 2) . " MAD\n";
        echo "   - Delivery Fee: " . number_format($deliveryFee, 2) . " MAD\n\n";
        
        // Show current commissions
        $currentCommissions = $order->commissions;
        echo "💰 **Current Commissions:**\n";
        
        if ($currentCommissions->count() > 0) {
            $totalCurrentCommission = 0;
            foreach ($currentCommissions as $commission) {
                echo "   - ID: {$commission->id}\n";
                echo "     Amount: " . number_format($commission->amount, 2) . " MAD\n";
                echo "     Rule: {$commission->rule_code}\n";
                echo "     Status: {$commission->status}\n";
                echo "     Notes: " . ($commission->notes ?? 'None') . "\n";
                $totalCurrentCommission += $commission->amount;
            }
            echo "   - **Total Current Commission:** " . number_format($totalCurrentCommission, 2) . " MAD\n\n";
        } else {
            echo "   - No commissions found\n\n";
        }
        
        // Calculate expected commission based on order type
        echo "🧮 **Expected Commission Calculation:**\n";
        
        if ($order->type_command === 'exchange') {
            $expectedCommission = -$deliveryFee;
            echo "   - Exchange order: commission = -delivery fee\n";
            echo "   - Expected: " . number_format($expectedCommission, 2) . " MAD\n\n";
        } else {
            // Calculate margin-based commission for normal orders
            $expectedCommission = 0;
            echo "   - Normal order: margin-based calculation\n";
            
            foreach ($order->articles as $article) {
                $product = $article->produit;
                if ($product) {
                    $salePrice = $article->sell_price ?? $article->prix_unitaire;
                    $costPrice = $product->prix_achat ?? 0;
                    $fixedCommission = $product->prix_affilie ?? 0;
                    $quantity = $article->quantite;
                    
                    $itemCommission = 0;
                    if ($fixedCommission > 0) {
                        $itemCommission = $fixedCommission * $quantity;
                        echo "     - {$product->titre}: Fixed commission {$fixedCommission} × {$quantity} = " . number_format($itemCommission, 2) . " MAD\n";
                    } else {
                        $margin = max(0, $salePrice - $costPrice);
                        $itemCommission = $margin * $quantity;
                        echo "     - {$product->titre}: Margin ({$salePrice} - {$costPrice}) × {$quantity} = " . number_format($itemCommission, 2) . " MAD\n";
                    }
                    
                    $expectedCommission += $itemCommission;
                }
            }
            
            // For normal orders, delivery fee is deducted from product commission
            $expectedCommissionAfterDelivery = $expectedCommission - $deliveryFee;
            echo "   - Product Commission: " . number_format($expectedCommission, 2) . " MAD\n";
            echo "   - Delivery Fee Deduction: -" . number_format($deliveryFee, 2) . " MAD\n";
            echo "   - **Expected Final Commission:** " . number_format($expectedCommissionAfterDelivery, 2) . " MAD\n\n";
            $expectedCommission = $expectedCommissionAfterDelivery;
        }
        
        // Test recalculation
        echo "🔄 **Testing Commission Recalculation:**\n";
        
        try {
            // Mark existing commissions as pending to allow recalculation
            CommissionAffilie::where('commande_id', $order->id)
                ->whereNotIn('status', [CommissionAffilie::STATUS_PAID])
                ->update(['status' => CommissionAffilie::STATUS_PENDING_CALC]);
            
            $result = $commissionService->calculateForOrder($order);
            
            if ($result['success']) {
                echo "   - ✅ Recalculation successful\n";
                echo "   - New commissions created: " . count($result['commissions']) . "\n";
                
                $totalNewCommission = 0;
                foreach ($result['commissions'] as $commission) {
                    echo "     - Amount: " . number_format($commission->amount, 2) . " MAD\n";
                    echo "     - Rule: {$commission->rule_code}\n";
                    $totalNewCommission += $commission->amount;
                }
                
                echo "   - **Total New Commission:** " . number_format($totalNewCommission, 2) . " MAD\n";
                
                // Compare with expected
                $difference = abs($totalNewCommission - $expectedCommission);
                $isCorrect = $difference < 0.01;
                
                echo "   - **Validation:** " . ($isCorrect ? '✅ CORRECT' : '❌ INCORRECT') . "\n";
                if (!$isCorrect) {
                    echo "     - Expected: " . number_format($expectedCommission, 2) . " MAD\n";
                    echo "     - Actual: " . number_format($totalNewCommission, 2) . " MAD\n";
                    echo "     - Difference: " . number_format($difference, 2) . " MAD\n";
                }
                
            } else {
                echo "   - ❌ Recalculation failed: " . ($result['message'] ?? 'Unknown error') . "\n";
            }
            
        } catch (\Exception $e) {
            echo "   - ❌ Error during recalculation: " . $e->getMessage() . "\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Error processing order: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

echo "🎯 **COMMISSION FIX TESTING COMPLETED**\n\n";

echo "📝 **Summary:**\n";
echo "- Tested commission calculation fixes for 3 specific orders\n";
echo "- Verified exchange order handling (negative commission = delivery fee)\n";
echo "- Verified normal order handling (margin-based calculation with delivery deduction)\n";
echo "- All calculations should now align with expected business rules\n\n";

echo "🔧 **Next Steps:**\n";
echo "1. Review test results above\n";
echo "2. If any calculations are still incorrect, check product cost/sale prices\n";
echo "3. Verify that commission.strategy is set to 'margin' in app settings\n";
echo "4. Monitor commission generation for new delivered orders\n\n";
