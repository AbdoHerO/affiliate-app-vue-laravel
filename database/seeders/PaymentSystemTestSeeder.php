<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use App\Models\CommissionAffilie;
use App\Services\WithdrawalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentSystemTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎯 Creating comprehensive payment system test data...');

        DB::transaction(function () {
            // Create edge case scenarios
            $this->createEdgeCaseScenarios();
            
            // Create bulk withdrawal scenarios
            $this->createBulkWithdrawalScenarios();
            
            // Create historical data for analytics
            $this->createHistoricalWithdrawalData();
            
            // Create commission adjustment scenarios
            $this->createCommissionAdjustmentScenarios();
        });

        $this->command->info('✅ Comprehensive payment system test data created!');
        $this->command->info('🔍 Test scenarios include:');
        $this->command->info('   • Edge cases (large amounts, many commissions)');
        $this->command->info('   • Bulk operations');
        $this->command->info('   • Historical data for analytics');
        $this->command->info('   • Commission adjustments');
    }

    /**
     * Create edge case scenarios for testing
     */
    private function createEdgeCaseScenarios(): void
    {
        $this->command->info('🔬 Creating edge case scenarios...');
        
        $affiliates = User::role('affiliate')->has('commissions')->take(2)->get();
        
        foreach ($affiliates as $affiliate) {
            // Scenario 1: Very large withdrawal with many commissions
            $this->createLargeWithdrawal($affiliate);
            
            // Scenario 2: Small withdrawal with single commission
            $this->createSmallWithdrawal($affiliate);
            
            // Scenario 3: Withdrawal in payment status (stuck in processing)
            $this->createInPaymentWithdrawal($affiliate);
        }
    }

    /**
     * Create a large withdrawal with many commissions
     */
    private function createLargeWithdrawal(User $affiliate): void
    {
        // Get many eligible commissions that are not already in withdrawal_items
        $commissions = CommissionAffilie::where('user_id', $affiliate->id)
            ->whereIn('status', ['approved', 'eligible'])
            ->whereNull('paid_withdrawal_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('withdrawal_items')
                      ->whereColumn('withdrawal_items.commission_id', 'commissions_affilies.id');
            })
            ->take(15)
            ->get();

        if ($commissions->count() < 10) {
            return; // Skip if not enough commissions
        }

        $totalAmount = $commissions->sum('amount');
        
        $withdrawal = Withdrawal::create([
            'user_id' => $affiliate->id,
            'amount' => $totalAmount,
            'status' => Withdrawal::STATUS_PENDING,
            'method' => Withdrawal::METHOD_BANK_TRANSFER,
            'iban_rib' => $affiliate->rib,
            'bank_type' => $affiliate->bank_type,
            'notes' => "Large withdrawal test - {$commissions->count()} commissions totaling {$totalAmount} MAD",
            'meta' => [
                'test_scenario' => 'large_withdrawal',
                'commission_count' => $commissions->count(),
                'average_commission' => round($totalAmount / $commissions->count(), 2),
            ],
            'created_at' => now()->subDays(2),
        ]);

        // Attach all commissions
        foreach ($commissions as $commission) {
            WithdrawalItem::create([
                'withdrawal_id' => $withdrawal->id,
                'commission_id' => $commission->id,
                'amount' => $commission->amount,
            ]);
        }

        $this->command->info("💰 Created large withdrawal: {$totalAmount} MAD ({$commissions->count()} commissions)");
    }

    /**
     * Create a small withdrawal with single commission
     */
    private function createSmallWithdrawal(User $affiliate): void
    {
        $commission = CommissionAffilie::where('user_id', $affiliate->id)
            ->whereIn('status', ['approved', 'eligible'])
            ->whereNull('paid_withdrawal_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('withdrawal_items')
                      ->whereColumn('withdrawal_items.commission_id', 'commissions_affilies.id');
            })
            ->orderBy('amount', 'asc')
            ->first();

        if (!$commission) {
            return;
        }

        $withdrawal = Withdrawal::create([
            'user_id' => $affiliate->id,
            'amount' => $commission->amount,
            'status' => Withdrawal::STATUS_APPROVED,
            'method' => Withdrawal::METHOD_BANK_TRANSFER,
            'iban_rib' => $affiliate->rib,
            'bank_type' => $affiliate->bank_type,
            'notes' => "Small withdrawal test - single commission of {$commission->amount} MAD",
            'approved_at' => now()->subHours(6),
            'meta' => [
                'test_scenario' => 'small_withdrawal',
                'single_commission' => true,
            ],
            'created_at' => now()->subDays(1),
        ]);

        WithdrawalItem::create([
            'withdrawal_id' => $withdrawal->id,
            'commission_id' => $commission->id,
            'amount' => $commission->amount,
        ]);

        // Reserve the commission
        $commission->update(['paid_withdrawal_id' => $withdrawal->id]);

        $this->command->info("💵 Created small withdrawal: {$commission->amount} MAD (1 commission)");
    }

    /**
     * Create withdrawal stuck in payment status
     */
    private function createInPaymentWithdrawal(User $affiliate): void
    {
        $commissions = CommissionAffilie::where('user_id', $affiliate->id)
            ->whereIn('status', ['approved', 'eligible'])
            ->whereNull('paid_withdrawal_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('withdrawal_items')
                      ->whereColumn('withdrawal_items.commission_id', 'commissions_affilies.id');
            })
            ->take(3)
            ->get();

        if ($commissions->count() < 2) {
            return;
        }

        $totalAmount = $commissions->sum('amount');
        
        $withdrawal = Withdrawal::create([
            'user_id' => $affiliate->id,
            'amount' => $totalAmount,
            'status' => Withdrawal::STATUS_IN_PAYMENT,
            'method' => Withdrawal::METHOD_BANK_TRANSFER,
            'iban_rib' => $affiliate->rib,
            'bank_type' => $affiliate->bank_type,
            'payment_ref' => 'PENDING-' . strtoupper(Str::random(8)),
            'notes' => "In payment test - processing for 2 days\n[" . now()->subDays(5)->format('Y-m-d H:i:s') . "] Approved by admin\n[" . now()->subDays(2)->format('Y-m-d H:i:s') . "] Marked as in payment",
            'approved_at' => now()->subDays(5),
            'meta' => [
                'test_scenario' => 'in_payment_stuck',
                'payment_initiated_at' => now()->subDays(2)->toISOString(),
            ],
            'created_at' => now()->subDays(7),
        ]);

        foreach ($commissions as $commission) {
            WithdrawalItem::create([
                'withdrawal_id' => $withdrawal->id,
                'commission_id' => $commission->id,
                'amount' => $commission->amount,
            ]);
            
            // Reserve the commission
            $commission->update(['paid_withdrawal_id' => $withdrawal->id]);
        }

        $this->command->info("⏳ Created in-payment withdrawal: {$totalAmount} MAD (stuck for 2 days)");
    }

    /**
     * Create bulk withdrawal scenarios
     */
    private function createBulkWithdrawalScenarios(): void
    {
        $this->command->info('📦 Creating bulk withdrawal scenarios...');
        
        // Create multiple withdrawals for the same day (bulk processing scenario)
        $targetDate = now()->subDays(10);
        $affiliates = User::role('affiliate')->has('commissions')->take(3)->get();
        
        foreach ($affiliates as $affiliate) {
            $commissions = CommissionAffilie::where('user_id', $affiliate->id)
                ->whereIn('status', ['approved', 'eligible'])
                ->whereNull('paid_withdrawal_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('withdrawal_items')
                          ->whereColumn('withdrawal_items.commission_id', 'commissions_affilies.id');
                })
                ->take(2)
                ->get();

            if ($commissions->count() < 2) {
                continue;
            }

            $withdrawal = Withdrawal::create([
                'user_id' => $affiliate->id,
                'amount' => $commissions->sum('amount'),
                'status' => Withdrawal::STATUS_PAID,
                'method' => Withdrawal::METHOD_BANK_TRANSFER,
                'iban_rib' => $affiliate->rib,
                'bank_type' => $affiliate->bank_type,
                'payment_ref' => 'BULK-' . $targetDate->format('Ymd') . '-' . strtoupper(Str::random(4)),
                'notes' => "Bulk processing batch - processed with other withdrawals on {$targetDate->format('Y-m-d')}",
                'approved_at' => $targetDate->copy()->subHours(2),
                'paid_at' => $targetDate,
                'meta' => [
                    'test_scenario' => 'bulk_processing',
                    'batch_date' => $targetDate->format('Y-m-d'),
                ],
                'created_at' => $targetDate->copy()->subDays(3),
            ]);

            foreach ($commissions as $commission) {
                WithdrawalItem::create([
                    'withdrawal_id' => $withdrawal->id,
                    'commission_id' => $commission->id,
                    'amount' => $commission->amount,
                ]);
                
                $commission->update([
                    'paid_withdrawal_id' => $withdrawal->id,
                    'status' => CommissionAffilie::STATUS_PAID,
                    'paid_at' => $targetDate,
                ]);
            }
        }

        $this->command->info("📊 Created bulk processing scenario for {$targetDate->format('Y-m-d')}");
    }

    /**
     * Create historical withdrawal data for analytics
     */
    private function createHistoricalWithdrawalData(): void
    {
        $this->command->info('📈 Creating historical withdrawal data...');
        
        $affiliates = User::role('affiliate')->has('commissions')->get();
        
        // Create withdrawals for the past 3 months
        for ($month = 1; $month <= 3; $month++) {
            $monthStart = now()->subMonths($month)->startOfMonth();
            $monthEnd = now()->subMonths($month)->endOfMonth();
            
            // Create 3-5 withdrawals per month
            for ($i = 0; $i < rand(3, 5); $i++) {
                $affiliate = $affiliates->random();
                $randomDate = $monthStart->copy()->addDays(rand(0, $monthStart->diffInDays($monthEnd)));
                
                $commissions = CommissionAffilie::where('user_id', $affiliate->id)
                    ->whereIn('status', ['approved', 'eligible'])
                    ->whereNull('paid_withdrawal_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                              ->from('withdrawal_items')
                              ->whereColumn('withdrawal_items.commission_id', 'commissions_affilies.id');
                    })
                    ->take(rand(2, 4))
                    ->get();

                if ($commissions->count() < 2) {
                    continue;
                }

                $withdrawal = Withdrawal::create([
                    'user_id' => $affiliate->id,
                    'amount' => $commissions->sum('amount'),
                    'status' => Withdrawal::STATUS_PAID,
                    'method' => Withdrawal::METHOD_BANK_TRANSFER,
                    'iban_rib' => $affiliate->rib,
                    'bank_type' => $affiliate->bank_type,
                    'payment_ref' => 'HIST-' . $randomDate->format('Ym') . '-' . strtoupper(Str::random(6)),
                    'notes' => "Historical withdrawal - {$randomDate->format('F Y')}",
                    'approved_at' => $randomDate->copy()->subDays(2),
                    'paid_at' => $randomDate,
                    'meta' => [
                        'test_scenario' => 'historical_data',
                        'month' => $randomDate->format('Y-m'),
                    ],
                    'created_at' => $randomDate->copy()->subDays(5),
                ]);

                foreach ($commissions as $commission) {
                    WithdrawalItem::create([
                        'withdrawal_id' => $withdrawal->id,
                        'commission_id' => $commission->id,
                        'amount' => $commission->amount,
                    ]);
                    
                    $commission->update([
                        'paid_withdrawal_id' => $withdrawal->id,
                        'status' => CommissionAffilie::STATUS_PAID,
                        'paid_at' => $randomDate,
                    ]);
                }
            }
        }

        $this->command->info("📅 Created historical data for past 3 months");
    }

    /**
     * Create commission adjustment scenarios
     */
    private function createCommissionAdjustmentScenarios(): void
    {
        $this->command->info('⚖️ Creating commission adjustment scenarios...');
        
        // Find some paid commissions and create adjustment scenarios
        $paidCommissions = CommissionAffilie::where('status', CommissionAffilie::STATUS_PAID)
            ->whereNotNull('paid_withdrawal_id')
            ->take(3)
            ->get();

        foreach ($paidCommissions as $commission) {
            $withdrawal = $commission->paidWithdrawal;
            if (!$withdrawal) continue;

            // Create a note about post-payment adjustment
            $adjustmentNote = "\n[" . now()->subDays(rand(1, 5))->format('Y-m-d H:i:s') . "] Post-payment adjustment: Commission was adjusted after payment due to customer return";
            
            $withdrawal->update([
                'notes' => $withdrawal->notes . $adjustmentNote,
                'meta' => array_merge($withdrawal->meta ?? [], [
                    'has_post_payment_adjustments' => true,
                    'adjustment_note' => 'Commission adjusted after payment - customer return processed',
                ])
            ]);
        }

        $this->command->info("📝 Created post-payment adjustment scenarios");
    }
}
