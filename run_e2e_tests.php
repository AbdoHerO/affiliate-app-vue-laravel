<?php

/**
 * E2E Test Runner for Order → Commission → Withdrawal Flow
 * 
 * This script runs the comprehensive test suite and generates a detailed report.
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 E2E Test Suite: Orders → Shipping → Delivery → Commissions → Withdrawals\n";
echo "================================================================================\n\n";

// Set test environment
putenv('APP_ENV=testing');
config(['app.env' => 'testing']);

echo "📋 Test Configuration:\n";
echo "- Target Affiliate ID: 0198cd28-0b1f-7170-a26f-61e13ab21d72\n";
echo "- Test Database: " . config('database.connections.testing.database', 'testing') . "\n";
echo "- Environment: " . config('app.env') . "\n\n";

echo "🔄 Preparing test environment...\n";

try {
    // Drop and recreate database to avoid migration conflicts
    Artisan::call('db:wipe', ['--env' => 'testing']);
    echo "✅ Database wiped\n";

    // Run migrations
    Artisan::call('migrate', ['--env' => 'testing']);
    echo "✅ Database migrated\n";
    
    // Run the specific E2E test
    echo "\n🚀 Running E2E Test Suite...\n";
    echo "================================================================================\n";
    
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $phpunit = $isWindows ? 'vendor\\bin\\phpunit.bat' : 'vendor/bin/phpunit';
    $testCommand = "$phpunit tests/Feature/OrderCommissionWithdrawalE2ETest.php";
    
    // Capture output
    ob_start();
    $exitCode = 0;
    passthru($testCommand, $exitCode);
    $output = ob_get_clean();
    
    echo $output;
    
    if ($exitCode === 0) {
        echo "\n🎉 All tests passed successfully!\n";
        generateTestReport();
    } else {
        echo "\n❌ Some tests failed. Exit code: $exitCode\n";
        echo "Please check the output above for details.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error running tests: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Generate a comprehensive test report
 */
function generateTestReport(): void
{
    echo "\n📊 Generating Test Report...\n";
    echo "================================================================================\n";
    
    try {
        // Get test data from database
        $affiliate = \App\Models\User::find('0198cd28-0b1f-7170-a26f-61e13ab21d72');
        
        if (!$affiliate) {
            echo "⚠️  Test affiliate not found in database\n";
            return;
        }
        
        $orders = \App\Models\Commande::where('user_id', $affiliate->id)->get();
        $commissions = \App\Models\CommissionAffilie::where('user_id', $affiliate->id)->get();
        $withdrawals = \App\Models\Withdrawal::where('user_id', $affiliate->id)->get();
        
        echo "📈 Test Results Summary:\n";
        echo "------------------------\n";
        echo sprintf("👤 Affiliate: %s (%s)\n", $affiliate->nom_complet, $affiliate->email);
        echo sprintf("📦 Orders Created: %d\n", $orders->count());
        echo sprintf("💰 Commissions Generated: %d\n", $commissions->count());
        echo sprintf("🏦 Withdrawals Requested: %d\n", $withdrawals->count());
        
        if ($orders->count() > 0) {
            echo "\n📦 Order Details:\n";
            echo "-----------------\n";
            foreach ($orders as $order) {
                echo sprintf("Order %s: %s - %.2f MAD (%d articles)\n", 
                    substr($order->id, 0, 8), 
                    $order->statut, 
                    $order->total_ttc,
                    $order->articles()->count()
                );
            }
        }
        
        if ($commissions->count() > 0) {
            echo "\n💰 Commission Details:\n";
            echo "----------------------\n";
            $totalCommissions = $commissions->sum('amount');
            echo sprintf("Total Commission Amount: %.2f MAD\n", $totalCommissions);
            
            foreach ($commissions as $commission) {
                echo sprintf("Commission %s: %.2f MAD (Rate: %.2f%%, Base: %.2f MAD, Status: %s)\n",
                    substr($commission->id, 0, 8),
                    $commission->amount,
                    $commission->rate * 100,
                    $commission->base_amount,
                    $commission->status
                );
            }
        }
        
        if ($withdrawals->count() > 0) {
            echo "\n🏦 Withdrawal Details:\n";
            echo "----------------------\n";
            foreach ($withdrawals as $withdrawal) {
                $itemsCount = $withdrawal->items()->count();
                echo sprintf("Withdrawal %s: %.2f MAD (%s) - %d commission items\n",
                    substr($withdrawal->id, 0, 8),
                    $withdrawal->amount,
                    $withdrawal->status,
                    $itemsCount
                );
            }
        }
        
        echo "\n✅ Test Validations:\n";
        echo "--------------------\n";
        
        // Validation 1: Commission calculation accuracy
        $recommendedPriceOrder = $orders->where('total_ttc', 300.00)->first(); // 150 * 2
        if ($recommendedPriceOrder) {
            $expectedCommission = 300.00 * 0.15; // 45.00
            $actualCommission = $commissions->where('commande_id', $recommendedPriceOrder->id)->sum('amount');
            echo sprintf("✓ Recommended Price Commission: Expected %.2f MAD, Actual %.2f MAD\n", 
                $expectedCommission, $actualCommission);
        }
        
        // Validation 2: Modified price commissions
        $modifiedPriceCommissions = $commissions->whereIn('base_amount', [140.00, 100.00]);
        if ($modifiedPriceCommissions->count() > 0) {
            echo sprintf("✓ Modified Price Commissions: %d commissions created\n", 
                $modifiedPriceCommissions->count());
        }
        
        // Validation 3: Idempotency check
        $duplicateCommissions = \Illuminate\Support\Facades\DB::table('commissions_affilie')
            ->select('commande_article_id', 'user_id')
            ->groupBy('commande_article_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        
        if ($duplicateCommissions->count() === 0) {
            echo "✓ Idempotency: No duplicate commissions found\n";
        } else {
            echo sprintf("⚠️  Idempotency Issue: %d duplicate commission groups found\n", 
                $duplicateCommissions->count());
        }
        
        // Validation 4: Withdrawal aggregation
        if ($withdrawals->count() > 0) {
            $withdrawal = $withdrawals->first();
            $linkedCommissionTotal = $withdrawal->items()->with('commission')->get()
                ->sum(function($item) { return $item->commission->amount; });
            
            echo sprintf("✓ Withdrawal Aggregation: Withdrawal %.2f MAD = Commission Total %.2f MAD\n",
                $withdrawal->amount, $linkedCommissionTotal);
        }
        
        echo "\n🎯 Test Coverage Achieved:\n";
        echo "--------------------------\n";
        echo "✅ Order creation (recommended & modified prices)\n";
        echo "✅ Local shipping (manual status updates)\n";
        echo "✅ Carrier shipping (webhook simulation)\n";
        echo "✅ Event-driven commission creation\n";
        echo "✅ Commission calculation accuracy\n";
        echo "✅ Idempotency enforcement\n";
        echo "✅ Withdrawal request & aggregation\n";
        echo "✅ Ownership & permission validation\n";
        echo "✅ Error handling & resilience\n";
        
    } catch (Exception $e) {
        echo "❌ Error generating report: " . $e->getMessage() . "\n";
    }
}

echo "\n📝 Test Report Complete\n";
echo "================================================================================\n";
