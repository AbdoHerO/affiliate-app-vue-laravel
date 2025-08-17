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

class WithdrawalDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎬 Creating demo withdrawal scenarios for testing...');

        DB::transaction(function () {
            // First create more approved commissions for testing
            $this->createMoreApprovedCommissions();
            
            // Create comprehensive withdrawal scenarios
            $this->createDemoWithdrawals();
        });

        $this->command->info('✅ Demo withdrawal scenarios created successfully!');
        $this->displayDemoInfo();
    }

    /**
     * Create more approved commissions for testing
     */
    private function createMoreApprovedCommissions(): void
    {
        $this->command->info('💰 Creating additional approved commissions...');
        
        $affiliates = User::role('affiliate')->get();
        
        foreach ($affiliates as $affiliate) {
            // Update some calculated commissions to approved status
            $calculatedCommissions = CommissionAffilie::where('user_id', $affiliate->id)
                ->where('status', 'calculated')
                ->take(15) // Increase to 15 for more demo data
                ->get();

            foreach ($calculatedCommissions as $commission) {
                $commission->update([
                    'status' => CommissionAffilie::STATUS_APPROVED,
                    'approved_at' => now()->subDays(rand(1, 30)),
                    'notes' => 'Auto-approved for demo purposes',
                ]);
            }

            // Also update eligible commissions
            $eligibleCommissions = CommissionAffilie::where('user_id', $affiliate->id)
                ->where('status', 'eligible')
                ->take(10)
                ->get();

            foreach ($eligibleCommissions as $commission) {
                $commission->update([
                    'status' => CommissionAffilie::STATUS_APPROVED,
                    'approved_at' => now()->subDays(rand(1, 20)),
                    'notes' => 'Eligible commission approved for demo',
                ]);
            }

            $totalCommissions = CommissionAffilie::where('user_id', $affiliate->id)->where('status', CommissionAffilie::STATUS_APPROVED)->count();
            $this->command->info("✅ {$affiliate->nom_complet} now has {$totalCommissions} approved commissions");
        }
    }

    /**
     * Create comprehensive demo withdrawals
     */
    private function createDemoWithdrawals(): void
    {
        $this->command->info('🎯 Creating demo withdrawal scenarios...');
        
        $withdrawalService = app(WithdrawalService::class);
        $affiliates = User::role('affiliate')->get();
        
        // Create exactly 5 withdrawals with different statuses
        $scenarios = [
            [
                'name' => 'Pending Review',
                'status' => Withdrawal::STATUS_PENDING,
                'description' => 'New withdrawal awaiting admin review',
                'commission_count' => 3,
            ],
            [
                'name' => 'Approved & Ready',
                'status' => Withdrawal::STATUS_APPROVED,
                'description' => 'Approved withdrawal ready for payment',
                'commission_count' => 2,
                'approved_days_ago' => 2,
            ],
            [
                'name' => 'In Payment',
                'status' => Withdrawal::STATUS_IN_PAYMENT,
                'description' => 'Payment in progress',
                'commission_count' => 4,
                'approved_days_ago' => 5,
                'payment_ref' => 'PAY-' . strtoupper(Str::random(8)),
            ],
            [
                'name' => 'Completed Payment',
                'status' => Withdrawal::STATUS_PAID,
                'description' => 'Successfully paid withdrawal',
                'commission_count' => 3,
                'approved_days_ago' => 10,
                'paid_days_ago' => 3,
                'payment_ref' => 'PAID-' . strtoupper(Str::random(8)),
            ],
            [
                'name' => 'Rejected Request',
                'status' => Withdrawal::STATUS_REJECTED,
                'description' => 'Rejected due to invalid bank info',
                'commission_count' => 2,
                'reject_reason' => 'Invalid bank account information provided',
            ],
        ];

        foreach ($scenarios as $index => $scenario) {
            // Cycle through affiliates, allowing multiple withdrawals per affiliate
            $affiliate = $affiliates[$index % $affiliates->count()];
            
            // Get available commissions for this affiliate
            $availableCommissions = CommissionAffilie::where('user_id', $affiliate->id)
                ->whereIn('status', ['approved', 'eligible'])
                ->whereNull('paid_withdrawal_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('withdrawal_items')
                          ->whereColumn('withdrawal_items.commission_id', 'commissions_affilies.id');
                })
                ->take($scenario['commission_count'])
                ->get();

            if ($availableCommissions->count() < 2) {
                $this->command->warn("⚠️  Skipping {$scenario['name']} - not enough commissions for {$affiliate->nom_complet}");
                continue;
            }

            $this->createScenarioWithdrawal($affiliate, $availableCommissions, $scenario, $withdrawalService);
        }
    }

    /**
     * Create a withdrawal for a specific scenario
     */
    private function createScenarioWithdrawal(User $affiliate, $commissions, array $scenario, WithdrawalService $withdrawalService): void
    {
        $totalAmount = $commissions->sum('amount');
        $createdAt = now()->subDays(rand(1, 15));
        
        // Create the withdrawal
        $withdrawal = Withdrawal::create([
            'user_id' => $affiliate->id,
            'amount' => $totalAmount,
            'status' => Withdrawal::STATUS_PENDING,
            'method' => Withdrawal::METHOD_BANK_TRANSFER,
            'iban_rib' => $affiliate->rib,
            'bank_type' => $affiliate->bank_type,
            'notes' => "Demo scenario: {$scenario['description']}",
            'meta' => [
                'demo_scenario' => $scenario['name'],
                'created_for_testing' => true,
            ],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        // Attach commissions
        foreach ($commissions as $commission) {
            WithdrawalItem::create([
                'withdrawal_id' => $withdrawal->id,
                'commission_id' => $commission->id,
                'amount' => $commission->amount,
            ]);
        }

        // Apply scenario-specific status and data
        $this->applyScenarioStatus($withdrawal, $scenario, $withdrawalService);

        $this->command->info("🎭 Created '{$scenario['name']}' scenario for {$affiliate->nom_complet}: {$totalAmount} MAD ({$commissions->count()} commissions)");
    }

    /**
     * Apply scenario-specific status and data
     */
    private function applyScenarioStatus(Withdrawal $withdrawal, array $scenario, WithdrawalService $withdrawalService): void
    {
        switch ($scenario['status']) {
            case Withdrawal::STATUS_PENDING:
                // Already pending, nothing to do
                break;

            case Withdrawal::STATUS_APPROVED:
                $withdrawalService->reserveCommissions($withdrawal);
                $withdrawal->update([
                    'approved_at' => now()->subDays($scenario['approved_days_ago'] ?? 1),
                    'notes' => $withdrawal->notes . "\n[" . now()->subDays($scenario['approved_days_ago'] ?? 1)->format('Y-m-d H:i:s') . "] Approved by admin - demo scenario",
                ]);
                break;

            case Withdrawal::STATUS_IN_PAYMENT:
                $withdrawalService->reserveCommissions($withdrawal);
                $withdrawal->update([
                    'status' => Withdrawal::STATUS_IN_PAYMENT,
                    'approved_at' => now()->subDays($scenario['approved_days_ago'] ?? 3),
                    'payment_ref' => $scenario['payment_ref'],
                    'notes' => $withdrawal->notes . "\n[" . now()->subDays($scenario['approved_days_ago'] ?? 3)->format('Y-m-d H:i:s') . "] Approved by admin\n[" . now()->subDays(1)->format('Y-m-d H:i:s') . "] Marked as in payment",
                ]);
                break;

            case Withdrawal::STATUS_PAID:
                $withdrawalService->reserveCommissions($withdrawal);
                $paidAt = now()->subDays($scenario['paid_days_ago'] ?? 1);
                $withdrawalService->markAsPaid($withdrawal, [
                    'paid_at' => $paidAt,
                    'payment_ref' => $scenario['payment_ref'],
                ]);
                $withdrawal->update([
                    'approved_at' => now()->subDays($scenario['approved_days_ago'] ?? 5),
                    'notes' => $withdrawal->notes . "\n[" . now()->subDays($scenario['approved_days_ago'] ?? 5)->format('Y-m-d H:i:s') . "] Approved by admin\n[" . $paidAt->format('Y-m-d H:i:s') . "] Marked as paid - demo scenario",
                ]);
                break;

            case Withdrawal::STATUS_REJECTED:
                $withdrawal->update([
                    'status' => Withdrawal::STATUS_REJECTED,
                    'admin_reason' => $scenario['reject_reason'],
                    'notes' => $withdrawal->notes . "\n[" . now()->subDays(1)->format('Y-m-d H:i:s') . "] Rejected by admin - demo scenario",
                ]);
                break;
        }
    }

    /**
     * Display demo information
     */
    private function displayDemoInfo(): void
    {
        $this->command->info('');
        $this->command->info('🎬 DEMO SCENARIOS CREATED:');
        $this->command->info('');

        $withdrawals = Withdrawal::with('user')->get();
        
        foreach ($withdrawals as $withdrawal) {
            $scenarioName = $withdrawal->meta['demo_scenario'] ?? 'Unknown';
            $statusColor = $this->getStatusColor($withdrawal->status);
            
            $this->command->info("   {$statusColor} {$scenarioName}");
            $this->command->info("     └─ {$withdrawal->user->nom_complet} - {$withdrawal->amount} MAD - {$withdrawal->status}");
        }

        $this->command->info('');
        $this->command->info('🎯 TESTING INSTRUCTIONS:');
        $this->command->info('   1. Visit /admin/withdrawals to see all scenarios');
        $this->command->info('   2. Try different actions on each withdrawal type:');
        $this->command->info('      • Approve pending withdrawals');
        $this->command->info('      • Reject withdrawals with reasons');
        $this->command->info('      • Mark approved withdrawals as in payment');
        $this->command->info('      • Mark in-payment withdrawals as paid');
        $this->command->info('   3. Test the commission selection when creating new withdrawals');
        $this->command->info('   4. Export withdrawals to CSV');
        $this->command->info('   5. Check commission status changes in /admin/commissions');
        $this->command->info('');
        $this->command->info('✨ Happy testing!');
    }

    /**
     * Get status color for display
     */
    private function getStatusColor(string $status): string
    {
        return match($status) {
            'pending' => '🟡',
            'approved' => '🔵',
            'in_payment' => '🟣',
            'paid' => '🟢',
            'rejected' => '🔴',
            'canceled' => '⚫',
            default => '⚪',
        };
    }
}
