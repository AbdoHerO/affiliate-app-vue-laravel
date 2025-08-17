<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Support\Str;

class ComprehensiveWithdrawalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Creating comprehensive withdrawal and commission data...');

        // Step 1: Ensure we have enough affiliates
        $this->ensureAffiliates();

        // Step 2: Run commission test seeder multiple times to get enough data
        $this->createCommissionsViaSeeder();

        // Step 3: Create 10 withdrawals with all statuses
        $this->createComprehensiveWithdrawals();

        $this->command->info('✅ Comprehensive withdrawal data created successfully!');
        $this->displaySummary();
    }

    /**
     * Ensure we have enough affiliate users
     */
    private function ensureAffiliates(): void
    {
        $affiliateRole = DB::table('roles')->where('name', 'affiliate')->first();
        if (!$affiliateRole) {
            $this->command->warn('⚠️ Affiliate role not found, skipping affiliate creation');
            return;
        }

        $existingAffiliates = User::role('affiliate')->count();
        
        if ($existingAffiliates < 10) {
            $this->command->info('👥 Creating additional affiliate users...');
            
            for ($i = $existingAffiliates + 1; $i <= 10; $i++) {
                $user = User::create([
                    'nom_complet' => "Affiliate Migration {$i}",
                    'email' => "affiliate.migration.{$i}@test.com",
                    'password' => bcrypt('password'),
                    'telephone' => "+212 6 00 00 00 " . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'statut' => 'actif',
                    'approval_status' => 'approved',
                    'email_verifie' => true,
                    'rib' => '1234567890123456789' . $i,
                    'bank_type' => ['Attijariwafa Bank', 'Banque Populaire', 'BMCE Bank', 'CIH Bank'][($i - 1) % 4],
                    'kyc_statut' => 'valide',
                ]);
                
                // Assign affiliate role
                $user->assignRole('affiliate');

                // Create affiliate profile
                $user->profilAffilie()->create([
                    'statut' => 'actif',
                    'gamme_id' => null, // No gamme for now
                    'points' => 0,
                    'rib' => $user->rib,
                ]);

                $this->command->info("✅ Created affiliate: {$user->nom_complet}");
            }
        }

        // Ensure all existing affiliates have profiles
        $affiliatesWithoutProfiles = User::role('affiliate')->whereDoesntHave('profilAffilie')->get();
        foreach ($affiliatesWithoutProfiles as $affiliate) {
            $affiliate->profilAffilie()->create([
                'statut' => 'actif',
                'gamme_id' => null, // No gamme for now
                'points' => 0,
                'rib' => $affiliate->rib,
            ]);
            $this->command->info("✅ Created profile for existing affiliate: {$affiliate->nom_complet}");
        }
    }

    /**
     * Create commissions via existing seeder
     */
    private function createCommissionsViaSeeder(): void
    {
        $this->command->info('💰 Creating commission data via existing seeder...');

        // Run the commission test seeder multiple times to get enough data
        for ($i = 0; $i < 3; $i++) {
            $this->call(CommissionTestSeeder::class);
            $this->command->info("✅ Commission batch " . ($i + 1) . " created");
        }

        // Update some commissions to approved status for withdrawal eligibility
        $this->updateCommissionsToApproved();
    }

    /**
     * Update existing commissions to approved status
     */
    private function updateCommissionsToApproved(): void
    {
        $this->command->info('📈 Updating commissions to approved status...');

        $affiliates = User::role('affiliate')->get();

        foreach ($affiliates as $affiliate) {
            // Update calculated commissions to approved
            $calculatedCommissions = CommissionAffilie::where('user_id', $affiliate->id)
                ->where('status', 'calculated')
                ->take(15)
                ->get();

            foreach ($calculatedCommissions as $commission) {
                $commission->update([
                    'status' => CommissionAffilie::STATUS_APPROVED,
                    'approved_at' => now()->subDays(rand(1, 30)),
                    'notes' => 'Auto-approved for withdrawal demo',
                ]);
            }

            // Update eligible commissions to approved
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

            $approvedCount = CommissionAffilie::where('user_id', $affiliate->id)
                ->where('status', CommissionAffilie::STATUS_APPROVED)
                ->count();

            $this->command->info("✅ {$affiliate->nom_complet}: {$approvedCount} approved commissions");
        }
    }

    /**
     * Create 10 comprehensive withdrawals with all statuses
     */
    private function createComprehensiveWithdrawals(): void
    {
        $this->command->info('🏦 Creating 10 comprehensive withdrawals...');
        
        $affiliates = User::role('affiliate')->get();
        $withdrawalService = app(WithdrawalService::class);
        
        // Define 10 comprehensive scenarios covering all statuses
        $scenarios = [
            [
                'name' => 'Fresh Pending Request',
                'status' => Withdrawal::STATUS_PENDING,
                'description' => 'New withdrawal request awaiting review',
                'commission_count' => 3,
                'created_days_ago' => 1,
            ],
            [
                'name' => 'Large Pending Request',
                'status' => Withdrawal::STATUS_PENDING,
                'description' => 'Large amount withdrawal pending approval',
                'commission_count' => 8,
                'created_days_ago' => 3,
            ],
            [
                'name' => 'Recently Approved',
                'status' => Withdrawal::STATUS_APPROVED,
                'description' => 'Recently approved withdrawal ready for payment',
                'commission_count' => 4,
                'created_days_ago' => 5,
                'approved_days_ago' => 1,
            ],
            [
                'name' => 'Approved Awaiting Payment',
                'status' => Withdrawal::STATUS_APPROVED,
                'description' => 'Approved withdrawal waiting for bank transfer',
                'commission_count' => 2,
                'created_days_ago' => 7,
                'approved_days_ago' => 3,
            ],
            [
                'name' => 'Payment Processing',
                'status' => Withdrawal::STATUS_IN_PAYMENT,
                'description' => 'Bank transfer initiated and in progress',
                'commission_count' => 5,
                'created_days_ago' => 10,
                'approved_days_ago' => 6,
                'payment_ref' => 'PAY-' . strtoupper(Str::random(8)),
            ],
            [
                'name' => 'Express Payment',
                'status' => Withdrawal::STATUS_IN_PAYMENT,
                'description' => 'Express payment processing',
                'commission_count' => 3,
                'created_days_ago' => 4,
                'approved_days_ago' => 2,
                'payment_ref' => 'EXPRESS-' . strtoupper(Str::random(6)),
            ],
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
                'name' => 'Monthly Payout',
                'status' => Withdrawal::STATUS_PAID,
                'description' => 'Regular monthly commission payout',
                'commission_count' => 10,
                'created_days_ago' => 30,
                'approved_days_ago' => 28,
                'paid_days_ago' => 20,
                'payment_ref' => 'MONTHLY-' . strtoupper(Str::random(6)),
            ],
            [
                'name' => 'Rejected - Invalid Bank',
                'status' => Withdrawal::STATUS_REJECTED,
                'description' => 'Rejected due to invalid bank information',
                'commission_count' => 2,
                'created_days_ago' => 8,
                'reject_reason' => 'Invalid bank account information. Please update your banking details.',
            ],
            [
                'name' => 'Cancelled Request',
                'status' => Withdrawal::STATUS_CANCELED,
                'description' => 'Cancelled by affiliate request',
                'commission_count' => 3,
                'created_days_ago' => 6,
                'reject_reason' => 'Cancelled by affiliate upon request',
            ],
        ];

        foreach ($scenarios as $index => $scenario) {
            $affiliate = $affiliates[$index % $affiliates->count()];
            
            // Get eligible commissions for this affiliate
            $eligibleCommissions = $withdrawalService->getEligibleCommissions($affiliate)
                ->take($scenario['commission_count'])
                ->get();
            
            if ($eligibleCommissions->count() < $scenario['commission_count']) {
                $this->command->warn("⚠️ Skipping {$scenario['name']} - not enough eligible commissions for {$affiliate->nom_complet}");
                continue;
            }
            
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
                    'seeder_batch' => '2025_08_17_comprehensive'
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
    }

    /**
     * Get random commission status based on distribution
     */
    private function getRandomCommissionStatus(int $index, int $total): string
    {
        $ratio = $index / $total;
        
        if ($ratio < 0.1) return CommissionAffilie::STATUS_PENDING_CALC;
        if ($ratio < 0.2) return CommissionAffilie::STATUS_CALCULATED;
        if ($ratio < 0.3) return CommissionAffilie::STATUS_ELIGIBLE;
        if ($ratio < 0.7) return CommissionAffilie::STATUS_APPROVED;
        if ($ratio < 0.85) return CommissionAffilie::STATUS_PAID;
        
        return CommissionAffilie::STATUS_APPROVED; // Default to approved for withdrawal eligibility
    }

    /**
     * Display summary of created data
     */
    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('📊 COMPREHENSIVE WITHDRAWAL DATA SUMMARY');
        $this->command->info('==========================================');
        
        // Withdrawal summary
        $withdrawals = Withdrawal::selectRaw('status, count(*) as count, sum(amount) as total_amount')
            ->groupBy('status')
            ->get();
            
        $this->command->info('🏦 WITHDRAWALS BY STATUS:');
        foreach ($withdrawals as $withdrawal) {
            $this->command->info("   • {$withdrawal->status}: {$withdrawal->count} withdrawals ({$withdrawal->total_amount} MAD)");
        }
        
        // Commission summary
        $commissions = CommissionAffilie::selectRaw('status, count(*) as count, sum(amount) as total_amount')
            ->groupBy('status')
            ->get();
            
        $this->command->info('');
        $this->command->info('💰 COMMISSIONS BY STATUS:');
        foreach ($commissions as $commission) {
            $this->command->info("   • {$commission->status}: {$commission->count} commissions ({$commission->total_amount} MAD)");
        }
        
        $this->command->info('');
        $this->command->info('🎯 TESTING URLS:');
        $this->command->info('   • Withdrawals List: /admin/withdrawals');
        $this->command->info('   • Create Withdrawal: /admin/withdrawals/create');
        $this->command->info('   • Commissions List: /admin/commissions');
        $this->command->info('');
        $this->command->info('✨ Ready for comprehensive testing!');
    }
}
