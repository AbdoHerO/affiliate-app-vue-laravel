<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use App\Models\CommissionAffilie;
use App\Services\WithdrawalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WithdrawalTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏦 Creating withdrawal test data...');

        DB::transaction(function () {
            // First ensure we have commission data
            $this->ensureCommissionData();
            
            // Add bank information to affiliate users
            $affiliates = $this->addBankInfoToAffiliates();
            
            // Create test withdrawals with different scenarios
            $this->createTestWithdrawals($affiliates);
            
            // Create some evidence files for paid withdrawals
            $this->createTestEvidenceFiles();
        });

        $this->command->info('✅ Withdrawal test data created successfully!');
        $this->command->info('💳 You can now test the complete payment & withdrawal system.');
        $this->command->info('📊 Check /admin/withdrawals to see the test data.');
    }

    /**
     * Ensure we have commission data to work with
     */
    private function ensureCommissionData(): void
    {
        $commissionCount = CommissionAffilie::count();
        
        if ($commissionCount < 10) {
            $this->command->warn('⚠️  Not enough commission data found. Running CommissionTestSeeder first...');
            $this->call(CommissionTestSeeder::class);
        }
        
        $this->command->info("📊 Found {$commissionCount} commissions to work with.");
    }

    /**
     * Add realistic bank information to affiliate users
     */
    private function addBankInfoToAffiliates(): array
    {
        $this->command->info('🏦 Adding bank information to affiliates...');
        
        $affiliates = User::role('affiliate')->get();
        $bankTypes = [
            'Attijariwafa Bank',
            'Banque Populaire',
            'BMCE Bank',
            'Crédit Agricole du Maroc',
            'Société Générale Maroc',
            'CIH Bank',
            'Bank of Africa',
            'Crédit du Maroc',
        ];

        foreach ($affiliates as $affiliate) {
            // Generate realistic Moroccan RIB (24 digits)
            $bankCode = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            $branchCode = str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $accountNumber = str_pad(rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
            $checkDigits = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
            $rib = $bankCode . $branchCode . $accountNumber . $checkDigits;

            $affiliate->update([
                'rib' => $rib,
                'bank_type' => $bankTypes[array_rand($bankTypes)],
            ]);

            $this->command->info("💳 Updated {$affiliate->nom_complet} with RIB: {$rib}");
        }

        return $affiliates->toArray();
    }

    /**
     * Create test withdrawals with different scenarios
     */
    private function createTestWithdrawals($affiliates): void
    {
        $this->command->info('💰 Creating test withdrawals...');
        
        $withdrawalService = app(WithdrawalService::class);
        
        foreach ($affiliates as $affiliate) {
            $user = User::find($affiliate['id']);
            
            // Get eligible commissions for this affiliate
            $eligibleCommissions = CommissionAffilie::where('user_id', $user->id)
                ->whereIn('status', ['approved', 'eligible'])
                ->whereNull('paid_withdrawal_id')
                ->get();

            if ($eligibleCommissions->count() < 2) {
                $this->command->info("⚠️  Skipping {$user->nom_complet} - not enough eligible commissions");
                continue;
            }

            // Create 2-3 withdrawals per affiliate with different scenarios
            $this->createWithdrawalScenarios($user, $eligibleCommissions, $withdrawalService);
        }
    }

    /**
     * Create different withdrawal scenarios for testing
     */
    private function createWithdrawalScenarios(User $user, $eligibleCommissions, WithdrawalService $withdrawalService): void
    {
        $scenarios = [
            [
                'type' => 'pending',
                'description' => 'Recent withdrawal request',
                'commission_count' => rand(2, 4),
                'created_days_ago' => rand(1, 3),
            ],
            [
                'type' => 'approved',
                'description' => 'Approved withdrawal awaiting payment',
                'commission_count' => rand(3, 6),
                'created_days_ago' => rand(5, 10),
                'approved_days_ago' => rand(1, 3),
            ],
            [
                'type' => 'paid',
                'description' => 'Completed withdrawal',
                'commission_count' => rand(2, 5),
                'created_days_ago' => rand(15, 30),
                'approved_days_ago' => rand(10, 20),
                'paid_days_ago' => rand(1, 8),
            ],
            [
                'type' => 'rejected',
                'description' => 'Rejected withdrawal',
                'commission_count' => rand(1, 3),
                'created_days_ago' => rand(7, 15),
            ],
        ];

        $availableCommissions = $eligibleCommissions->shuffle();
        $usedCommissions = 0;

        foreach ($scenarios as $index => $scenario) {
            if ($usedCommissions >= $availableCommissions->count()) {
                break; // No more commissions available
            }

            $commissionsForWithdrawal = $availableCommissions->slice($usedCommissions, $scenario['commission_count']);
            $usedCommissions += $scenario['commission_count'];

            if ($commissionsForWithdrawal->count() === 0) {
                continue;
            }

            $this->createWithdrawalWithScenario($user, $commissionsForWithdrawal, $scenario, $withdrawalService);
        }
    }

    /**
     * Create a withdrawal with specific scenario
     */
    private function createWithdrawalWithScenario(User $user, $commissions, array $scenario, WithdrawalService $withdrawalService): void
    {
        $totalAmount = $commissions->sum('amount');
        
        // Create withdrawal
        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'amount' => $totalAmount,
            'status' => Withdrawal::STATUS_PENDING,
            'method' => Withdrawal::METHOD_BANK_TRANSFER,
            'iban_rib' => $user->rib,
            'bank_type' => $user->bank_type,
            'notes' => "Test withdrawal - {$scenario['description']}",
            'meta' => [
                'created_by_admin' => true,
                'test_scenario' => $scenario['type'],
                'user_snapshot' => [
                    'nom_complet' => $user->nom_complet,
                    'email' => $user->email,
                    'rib' => $user->rib,
                    'bank_type' => $user->bank_type,
                ]
            ],
            'created_at' => now()->subDays($scenario['created_days_ago']),
            'updated_at' => now()->subDays($scenario['created_days_ago']),
        ]);

        // Attach commissions
        foreach ($commissions as $commission) {
            WithdrawalItem::create([
                'withdrawal_id' => $withdrawal->id,
                'commission_id' => $commission->id,
                'amount' => $commission->amount,
            ]);
        }

        // Apply scenario-specific status changes
        $this->applyWithdrawalScenario($withdrawal, $commissions, $scenario, $withdrawalService);

        $this->command->info("💸 Created {$scenario['type']} withdrawal for {$user->nom_complet}: {$totalAmount} MAD ({$commissions->count()} commissions)");
    }

    /**
     * Apply specific scenario status and data
     */
    private function applyWithdrawalScenario(Withdrawal $withdrawal, $commissions, array $scenario, WithdrawalService $withdrawalService): void
    {
        switch ($scenario['type']) {
            case 'pending':
                // Already pending, nothing to do
                break;

            case 'approved':
                // Reserve commissions and approve
                $withdrawalService->reserveCommissions($withdrawal);
                $withdrawal->update([
                    'approved_at' => now()->subDays($scenario['approved_days_ago']),
                    'notes' => $withdrawal->notes . "\n[" . now()->subDays($scenario['approved_days_ago'])->format('Y-m-d H:i:s') . "] Approved by admin - test scenario",
                ]);
                break;

            case 'paid':
                // Reserve, approve, and mark as paid
                $withdrawalService->reserveCommissions($withdrawal);
                $withdrawal->update([
                    'approved_at' => now()->subDays($scenario['approved_days_ago']),
                ]);
                
                $withdrawalService->markAsPaid($withdrawal, [
                    'paid_at' => now()->subDays($scenario['paid_days_ago']),
                    'payment_ref' => 'TEST-PAY-' . strtoupper(Str::random(8)),
                    'evidence_path' => 'withdrawals/test_evidence_' . $withdrawal->id . '.pdf',
                ]);
                
                $withdrawal->update([
                    'notes' => $withdrawal->notes . "\n[" . now()->subDays($scenario['approved_days_ago'])->format('Y-m-d H:i:s') . "] Approved by admin\n[" . now()->subDays($scenario['paid_days_ago'])->format('Y-m-d H:i:s') . "] Marked as paid - test scenario",
                ]);
                break;

            case 'rejected':
                $withdrawal->update([
                    'status' => Withdrawal::STATUS_REJECTED,
                    'admin_reason' => 'Test rejection - Invalid bank information provided',
                    'notes' => $withdrawal->notes . "\n[" . now()->subDays(2)->format('Y-m-d H:i:s') . "] Rejected by admin - test scenario",
                ]);
                break;
        }
    }

    /**
     * Create test evidence files for paid withdrawals
     */
    private function createTestEvidenceFiles(): void
    {
        $this->command->info('📄 Creating test evidence files...');
        
        $paidWithdrawals = Withdrawal::where('status', Withdrawal::STATUS_PAID)
            ->whereNotNull('evidence_path')
            ->get();

        foreach ($paidWithdrawals as $withdrawal) {
            $evidencePath = $withdrawal->evidence_path;
            
            // Create a simple text file as evidence (in real scenario, this would be a PDF or image)
            $evidenceContent = "PAYMENT EVIDENCE - TEST FILE\n\n";
            $evidenceContent .= "Withdrawal ID: {$withdrawal->id}\n";
            $evidenceContent .= "Affiliate: {$withdrawal->user->nom_complet}\n";
            $evidenceContent .= "Amount: {$withdrawal->amount} MAD\n";
            $evidenceContent .= "Payment Reference: {$withdrawal->payment_ref}\n";
            $evidenceContent .= "Payment Date: {$withdrawal->paid_at}\n";
            $evidenceContent .= "Bank: {$withdrawal->bank_type}\n";
            $evidenceContent .= "RIB: {$withdrawal->iban_rib}\n\n";
            $evidenceContent .= "This is a test evidence file generated by the seeder.\n";
            $evidenceContent .= "In production, this would be a bank transfer receipt or screenshot.\n";

            Storage::disk('local')->put($evidencePath, $evidenceContent);
        }

        $this->command->info("📎 Created {$paidWithdrawals->count()} test evidence files");
    }
}
