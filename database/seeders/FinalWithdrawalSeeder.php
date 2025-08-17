<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Support\Str;

class FinalWithdrawalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎯 Creating final withdrawals to reach 10 total...');
        
        $withdrawalService = app(WithdrawalService::class);
        
        // Update more commissions to approved first
        $this->updateMoreCommissionsToApproved();
        
        // Create the final 2 withdrawal scenarios
        $scenarios = [
            [
                'name' => 'Successfully Completed',
                'status' => Withdrawal::STATUS_PAID,
                'description' => 'Successfully paid withdrawal',
                'commission_count' => 6,
                'created_days_ago' => 15,
                'approved_days_ago' => 12,
                'paid_days_ago' => 5,
                'payment_ref' => 'PAID-' . strtoupper(Str::random(8)),
            ],
            [
                'name' => 'Rejected - Invalid Bank',
                'status' => Withdrawal::STATUS_REJECTED,
                'description' => 'Rejected due to invalid bank information',
                'commission_count' => 2,
                'created_days_ago' => 8,
                'reject_reason' => 'Invalid bank account information. Please update your banking details.',
            ],
        ];
        
        $affiliates = User::role('affiliate')->get();
        
        foreach ($scenarios as $index => $scenario) {
            // Find an affiliate with enough commissions
            $affiliate = null;
            foreach ($affiliates as $potentialAffiliate) {
                $eligibleCommissions = $withdrawalService->getEligibleCommissions($potentialAffiliate)
                    ->take($scenario['commission_count'])
                    ->get();
                    
                if ($eligibleCommissions->count() >= $scenario['commission_count']) {
                    $affiliate = $potentialAffiliate;
                    break;
                }
            }
            
            if (!$affiliate) {
                $this->command->warn("⚠️ Skipping {$scenario['name']} - no affiliate with enough commissions");
                continue;
            }
            
            $eligibleCommissions = $withdrawalService->getEligibleCommissions($affiliate)
                ->take($scenario['commission_count'])
                ->get();
            
            // Calculate total amount
            $totalAmount = $eligibleCommissions->sum('amount');
            
            // Create withdrawal
            $withdrawal = Withdrawal::create([
                'user_id' => $affiliate->id,
                'amount' => $totalAmount,
                'status' => $scenario['status'],
                'method' => 'bank_transfer',
                'iban_rib' => $affiliate->rib,
                'bank_type' => $affiliate->bank_type,
                'notes' => $scenario['description'],
                'admin_reason' => $scenario['reject_reason'] ?? null,
                'payment_ref' => $scenario['payment_ref'] ?? null,
                'evidence_path' => null,
                'approved_at' => isset($scenario['approved_days_ago']) ? now()->subDays($scenario['approved_days_ago']) : null,
                'paid_at' => isset($scenario['paid_days_ago']) ? now()->subDays($scenario['paid_days_ago']) : null,
                'meta' => json_encode([
                    'created_by_seeder' => true,
                    'demo_scenario' => $scenario['name'],
                    'seeder_batch' => '2025_08_17_final'
                ]),
                'created_at' => now()->subDays($scenario['created_days_ago']),
                'updated_at' => now()->subDays($scenario['created_days_ago'] - 1),
            ]);
            
            // Link commissions to withdrawal
            foreach ($eligibleCommissions as $commission) {
                $commission->update([
                    'paid_withdrawal_id' => $withdrawal->id,
                    'status' => $scenario['status'] === Withdrawal::STATUS_PAID ? CommissionAffilie::STATUS_PAID : CommissionAffilie::STATUS_APPROVED,
                    'paid_at' => $scenario['status'] === Withdrawal::STATUS_PAID ? $withdrawal->paid_at : null,
                ]);
            }
            
            $this->command->info("✅ Created '{$scenario['name']}' for {$affiliate->nom_complet}: {$totalAmount} MAD ({$eligibleCommissions->count()} commissions)");
        }
        
        // Display final summary
        $totalWithdrawals = Withdrawal::count();
        $this->command->info("🎉 Final withdrawal count: {$totalWithdrawals}");
        
        if ($totalWithdrawals >= 10) {
            $this->command->info("✅ Target of 10 withdrawals achieved!");
        } else {
            $this->command->warn("⚠️ Only {$totalWithdrawals} withdrawals created. Need " . (10 - $totalWithdrawals) . " more.");
        }
    }
    
    /**
     * Update more commissions to approved status
     */
    private function updateMoreCommissionsToApproved(): void
    {
        $this->command->info('📈 Updating more commissions to approved status...');
        
        $affiliates = User::role('affiliate')->get();
        
        foreach ($affiliates as $affiliate) {
            // Update calculated commissions to approved
            $calculatedCommissions = CommissionAffilie::where('user_id', $affiliate->id)
                ->where('status', 'calculated')
                ->take(20) // More commissions
                ->get();

            foreach ($calculatedCommissions as $commission) {
                $commission->update([
                    'status' => CommissionAffilie::STATUS_APPROVED,
                    'approved_at' => now()->subDays(rand(1, 30)),
                    'notes' => 'Auto-approved for final withdrawal demo',
                ]);
            }

            // Update eligible commissions to approved
            $eligibleCommissions = CommissionAffilie::where('user_id', $affiliate->id)
                ->where('status', 'eligible')
                ->take(15) // More commissions
                ->get();

            foreach ($eligibleCommissions as $commission) {
                $commission->update([
                    'status' => CommissionAffilie::STATUS_APPROVED,
                    'approved_at' => now()->subDays(rand(1, 20)),
                    'notes' => 'Eligible commission approved for final demo',
                ]);
            }

            $approvedCount = CommissionAffilie::where('user_id', $affiliate->id)
                ->where('status', CommissionAffilie::STATUS_APPROVED)
                ->count();
                
            if ($approvedCount > 0) {
                $this->command->info("✅ {$affiliate->nom_complet}: {$approvedCount} approved commissions");
            }
        }
    }
}
