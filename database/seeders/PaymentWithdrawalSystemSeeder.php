<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentWithdrawalSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Payment & Withdrawal System Test Data Creation...');
        $this->command->info('');

        // Check if we're in a safe environment
        if (app()->environment('production')) {
            $this->command->error('❌ Cannot run test seeders in production environment!');
            return;
        }

        $startTime = microtime(true);

        try {
            DB::beginTransaction();

            // Step 1: Ensure we have basic commission data
            $this->command->info('📊 Step 1: Ensuring commission test data exists...');
            $this->call(CommissionTestSeeder::class);
            $this->command->info('');

            // Step 2: Create withdrawal test data
            $this->command->info('💰 Step 2: Creating withdrawal test scenarios...');
            $this->call(WithdrawalTestSeeder::class);
            $this->command->info('');

            // Step 3: Create comprehensive payment system scenarios
            $this->command->info('🎯 Step 3: Creating comprehensive payment system scenarios...');
            $this->call(PaymentSystemTestSeeder::class);
            $this->command->info('');

            DB::commit();

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            $this->displaySummary($executionTime);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error occurred during seeding: ' . $e->getMessage());
            $this->command->error('🔄 All changes have been rolled back.');
            throw $e;
        }
    }

    /**
     * Display a summary of created test data
     */
    private function displaySummary(float $executionTime): void
    {
        $this->command->info('');
        $this->command->info('🎉 Payment & Withdrawal System Test Data Created Successfully!');
        $this->command->info('⏱️  Execution time: ' . $executionTime . ' seconds');
        $this->command->info('');

        // Get statistics
        $stats = $this->getSystemStats();

        $this->command->info('📈 SYSTEM STATISTICS:');
        $this->command->info('');
        
        // Users & Affiliates
        $this->command->info('👥 USERS & AFFILIATES:');
        $this->command->info("   • Total Affiliates: {$stats['affiliates_count']}");
        $this->command->info("   • Affiliates with Bank Info: {$stats['affiliates_with_bank']}");
        $this->command->info('');

        // Commissions
        $this->command->info('💼 COMMISSIONS:');
        $this->command->info("   • Total Commissions: {$stats['total_commissions']}");
        $this->command->info("   • Eligible for Withdrawal: {$stats['eligible_commissions']}");
        $this->command->info("   • Already Paid: {$stats['paid_commissions']}");
        $this->command->info("   • Reserved in Withdrawals: {$stats['reserved_commissions']}");
        $this->command->info('');

        // Withdrawals
        $this->command->info('💰 WITHDRAWALS:');
        $this->command->info("   • Total Withdrawals: {$stats['total_withdrawals']}");
        foreach ($stats['withdrawals_by_status'] as $status => $count) {
            $statusLabel = ucfirst(str_replace('_', ' ', $status));
            $this->command->info("   • {$statusLabel}: {$count}");
        }
        $this->command->info("   • Total Amount: {$stats['total_withdrawal_amount']} MAD");
        $this->command->info('');

        // Test Scenarios
        $this->command->info('🧪 TEST SCENARIOS CREATED:');
        $this->command->info('   • Basic withdrawal workflows (pending → approved → paid)');
        $this->command->info('   • Rejection scenarios with reasons');
        $this->command->info('   • Large withdrawals with many commissions');
        $this->command->info('   • Small single-commission withdrawals');
        $this->command->info('   • Stuck in-payment scenarios');
        $this->command->info('   • Bulk processing batches');
        $this->command->info('   • Historical data for analytics (3 months)');
        $this->command->info('   • Post-payment adjustment scenarios');
        $this->command->info('   • Evidence files for paid withdrawals');
        $this->command->info('');

        // Next Steps
        $this->command->info('🎯 NEXT STEPS:');
        $this->command->info('   1. Visit /admin/withdrawals to see the withdrawal management interface');
        $this->command->info('   2. Test different withdrawal actions (approve, reject, mark paid)');
        $this->command->info('   3. Try creating new withdrawals with commission selection');
        $this->command->info('   4. Test the export functionality');
        $this->command->info('   5. Check /admin/commissions to see linked commission data');
        $this->command->info('');

        $this->command->info('✨ Happy testing!');
    }

    /**
     * Get system statistics for summary
     */
    private function getSystemStats(): array
    {
        return [
            // Users
            'affiliates_count' => \App\Models\User::role('affiliate')->count(),
            'affiliates_with_bank' => \App\Models\User::role('affiliate')->whereNotNull('rib')->count(),

            // Commissions
            'total_commissions' => \App\Models\CommissionAffilie::count(),
            'eligible_commissions' => \App\Models\CommissionAffilie::whereIn('status', ['approved', 'eligible'])
                ->whereNull('paid_withdrawal_id')->count(),
            'paid_commissions' => \App\Models\CommissionAffilie::where('status', 'paid')->count(),
            'reserved_commissions' => \App\Models\CommissionAffilie::whereNotNull('paid_withdrawal_id')->count(),

            // Withdrawals
            'total_withdrawals' => \App\Models\Withdrawal::count(),
            'withdrawals_by_status' => \App\Models\Withdrawal::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'total_withdrawal_amount' => \App\Models\Withdrawal::sum('amount'),
        ];
    }
}
