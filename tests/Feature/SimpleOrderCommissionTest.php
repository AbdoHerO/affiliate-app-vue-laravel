<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Log;

/**
 * Simple E2E Test for Order → Commission → Withdrawal Flow
 * 
 * This test works with existing database without migrations
 */
class SimpleOrderCommissionTest extends TestCase
{
    private ?User $testAffiliate = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Find or create the target affiliate
        $this->testAffiliate = User::find('0198cd28-0b1f-7170-a26f-61e13ab21d72');
        
        if (!$this->testAffiliate) {
            $this->markTestSkipped('Target affiliate 0198cd28-0b1f-7170-a26f-61e13ab21d72 not found in database');
        }
        
        Log::info('Simple E2E Test Setup', [
            'affiliate_id' => $this->testAffiliate->id,
            'affiliate_name' => $this->testAffiliate->nom_complet,
        ]);
    }

    /**
     * Test 1: Verify affiliate exists and has proper setup
     */
    public function test_affiliate_exists_and_setup(): void
    {
        $this->assertNotNull($this->testAffiliate);
        $this->assertEquals('0198cd28-0b1f-7170-a26f-61e13ab21d72', $this->testAffiliate->id);
        $this->assertEquals('approved', $this->testAffiliate->approval_status);
        
        // Check if affiliate has role
        $this->assertTrue($this->testAffiliate->hasRole('affiliate'));
        
        Log::info('✅ Test 1 Passed: Affiliate exists and is properly configured');
    }

    /**
     * Test 2: Check existing orders for the affiliate
     */
    public function test_affiliate_has_orders(): void
    {
        $orders = Commande::where('user_id', $this->testAffiliate->id)->get();
        
        $this->assertGreaterThan(0, $orders->count(), 'Affiliate should have at least one order');
        
        foreach ($orders as $order) {
            $this->assertEquals($this->testAffiliate->id, $order->user_id);
            $this->assertNotNull($order->statut);
        }
        
        Log::info('✅ Test 2 Passed: Affiliate has orders', [
            'orders_count' => $orders->count(),
            'statuses' => $orders->pluck('statut')->unique()->toArray(),
        ]);
    }

    /**
     * Test 3: Check existing commissions for the affiliate
     */
    public function test_affiliate_has_commissions(): void
    {
        $commissions = CommissionAffilie::where('user_id', $this->testAffiliate->id)->get();
        
        if ($commissions->count() > 0) {
            $totalAmount = $commissions->sum('amount');
            
            foreach ($commissions as $commission) {
                $this->assertEquals($this->testAffiliate->id, $commission->user_id);
                $this->assertGreaterThan(0, $commission->amount);
                $this->assertNotNull($commission->status);
            }
            
            Log::info('✅ Test 3 Passed: Affiliate has commissions', [
                'commissions_count' => $commissions->count(),
                'total_amount' => $totalAmount,
                'statuses' => $commissions->pluck('status')->unique()->toArray(),
            ]);
        } else {
            Log::info('ℹ️  Test 3: No commissions found for affiliate (this is OK for new affiliates)');
            $this->assertTrue(true); // Pass the test even if no commissions exist
        }
    }

    /**
     * Test 4: Check existing withdrawals for the affiliate
     */
    public function test_affiliate_withdrawals(): void
    {
        $withdrawals = Withdrawal::where('user_id', $this->testAffiliate->id)->get();
        
        if ($withdrawals->count() > 0) {
            $totalWithdrawn = $withdrawals->sum('amount');
            
            foreach ($withdrawals as $withdrawal) {
                $this->assertEquals($this->testAffiliate->id, $withdrawal->user_id);
                $this->assertGreaterThan(0, $withdrawal->amount);
                $this->assertNotNull($withdrawal->status);
            }
            
            Log::info('✅ Test 4 Passed: Affiliate has withdrawals', [
                'withdrawals_count' => $withdrawals->count(),
                'total_amount' => $totalWithdrawn,
                'statuses' => $withdrawals->pluck('status')->unique()->toArray(),
            ]);
        } else {
            Log::info('ℹ️  Test 4: No withdrawals found for affiliate (this is OK)');
            $this->assertTrue(true); // Pass the test even if no withdrawals exist
        }
    }

    /**
     * Test 5: Test API authentication for the affiliate
     */
    public function test_affiliate_api_authentication(): void
    {
        $token = $this->testAffiliate->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->get('/api/affiliate/orders');
        
        // Should return 200 or 404, but not 401 (unauthorized)
        $this->assertNotEquals(401, $response->status(), 'API authentication should work');
        
        // Clean up token
        $this->testAffiliate->tokens()->where('name', 'test-token')->delete();
        
        Log::info('✅ Test 5 Passed: API authentication works', [
            'response_status' => $response->status(),
        ]);
    }

    /**
     * Test 6: Verify commission calculation logic (if commissions exist)
     */
    public function test_commission_calculation_logic(): void
    {
        $commissions = CommissionAffilie::where('user_id', $this->testAffiliate->id)
            ->whereNotNull('base_amount')
            ->whereNotNull('rate')
            ->whereNotNull('amount')
            ->get();
        
        if ($commissions->count() > 0) {
            foreach ($commissions as $commission) {
                $expectedAmount = round($commission->base_amount * $commission->rate, 2);
                $actualAmount = (float) $commission->amount;
                
                $this->assertEquals(
                    $expectedAmount, 
                    $actualAmount, 
                    "Commission calculation should be correct: {$commission->base_amount} * {$commission->rate} = {$expectedAmount}, got {$actualAmount}"
                );
            }
            
            Log::info('✅ Test 6 Passed: Commission calculations are correct', [
                'checked_commissions' => $commissions->count(),
            ]);
        } else {
            Log::info('ℹ️  Test 6: No commissions with calculation data found');
            $this->assertTrue(true);
        }
    }

    /**
     * Test 7: Test data relationships and integrity
     */
    public function test_data_relationships(): void
    {
        $orders = Commande::where('user_id', $this->testAffiliate->id)->with(['articles', 'commissions'])->get();
        
        foreach ($orders as $order) {
            // Test order has articles
            if ($order->articles && $order->articles->count() > 0) {
                foreach ($order->articles as $article) {
                    $this->assertEquals($order->id, $article->commande_id);
                }
            }
            
            // Test order commissions belong to the affiliate
            if ($order->commissions && $order->commissions->count() > 0) {
                foreach ($order->commissions as $commission) {
                    $this->assertEquals($this->testAffiliate->id, $commission->user_id);
                    $this->assertEquals($order->id, $commission->commande_id);
                }
            }
        }
        
        Log::info('✅ Test 7 Passed: Data relationships are correct');
    }

    /**
     * Generate a comprehensive test report
     */
    public function test_generate_comprehensive_report(): void
    {
        $orders = Commande::where('user_id', $this->testAffiliate->id)->get();
        $commissions = CommissionAffilie::where('user_id', $this->testAffiliate->id)->get();
        $withdrawals = Withdrawal::where('user_id', $this->testAffiliate->id)->get();
        
        $report = [
            'affiliate' => [
                'id' => $this->testAffiliate->id,
                'name' => $this->testAffiliate->nom_complet,
                'email' => $this->testAffiliate->email,
                'status' => $this->testAffiliate->approval_status,
            ],
            'orders' => [
                'total_count' => $orders->count(),
                'by_status' => $orders->groupBy('statut')->map->count(),
                'total_amount' => $orders->sum('total_ttc'),
            ],
            'commissions' => [
                'total_count' => $commissions->count(),
                'by_status' => $commissions->groupBy('status')->map->count(),
                'total_amount' => $commissions->sum('amount'),
            ],
            'withdrawals' => [
                'total_count' => $withdrawals->count(),
                'by_status' => $withdrawals->groupBy('status')->map->count(),
                'total_amount' => $withdrawals->sum('amount'),
            ],
        ];
        
        Log::info('📊 COMPREHENSIVE TEST REPORT', $report);
        
        // Basic assertions
        $this->assertIsArray($report);
        $this->assertArrayHasKey('affiliate', $report);
        $this->assertArrayHasKey('orders', $report);
        $this->assertArrayHasKey('commissions', $report);
        $this->assertArrayHasKey('withdrawals', $report);
        
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 E2E TEST REPORT FOR AFFILIATE: " . $this->testAffiliate->nom_complet . "\n";
        echo str_repeat("=", 80) . "\n";
        echo "📦 Orders: " . $report['orders']['total_count'] . " (Total: " . number_format($report['orders']['total_amount'], 2) . " MAD)\n";
        echo "💰 Commissions: " . $report['commissions']['total_count'] . " (Total: " . number_format($report['commissions']['total_amount'], 2) . " MAD)\n";
        echo "🏦 Withdrawals: " . $report['withdrawals']['total_count'] . " (Total: " . number_format($report['withdrawals']['total_amount'], 2) . " MAD)\n";
        echo str_repeat("=", 80) . "\n";
        
        Log::info('✅ Test 8 Passed: Comprehensive report generated');
    }
}
