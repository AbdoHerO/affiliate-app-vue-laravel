<?php

namespace App\Console\Commands;

use App\Models\CommissionAffilie;
use App\Models\Commande;
use App\Models\User;
use App\Services\CommissionService;
use Database\Seeders\CommissionTestSeeder;
use Illuminate\Console\Command;

class SetupCommissionTestDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commissions:setup-test-data {--fresh : Clear existing commission data first}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup comprehensive test data for commission management system';

    /**
     * Execute the console command.
     */
    public function handle(CommissionService $commissionService)
    {
        $this->info('🚀 Setting up Commission Test Data');
        $this->info('=====================================');

        if ($this->option('fresh')) {
            $this->warn('⚠️  Clearing existing commission data...');
            
            if ($this->confirm('This will delete all existing commissions. Continue?')) {
                CommissionAffilie::truncate();
                $this->info('✅ Existing commission data cleared.');
            } else {
                $this->info('❌ Operation cancelled.');
                return 0;
            }
        }

        // Run the seeder
        $this->info('📊 Running Commission Test Seeder...');
        $seeder = new CommissionTestSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        // Display summary
        $this->displaySummary();

        // Provide next steps
        $this->displayNextSteps();

        return 0;
    }

    /**
     * Display summary of created data
     */
    private function displaySummary(): void
    {
        $this->info('');
        $this->info('📈 COMMISSION TEST DATA SUMMARY');
        $this->info('===============================');

        // Count commissions by status
        $commissionCounts = CommissionAffilie::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalCommissions = array_sum($commissionCounts);
        $totalAmount = CommissionAffilie::sum('amount');

        $this->table(
            ['Status', 'Count', 'Percentage'],
            collect($commissionCounts)->map(function ($count, $status) use ($totalCommissions) {
                $percentage = $totalCommissions > 0 ? round(($count / $totalCommissions) * 100, 1) : 0;
                return [
                    $status,
                    $count,
                    $percentage . '%'
                ];
            })->toArray()
        );

        $this->info("💰 Total Commissions: {$totalCommissions}");
        $this->info("💵 Total Amount: " . number_format($totalAmount, 2) . " MAD");

        // Count orders by status
        $orderCounts = Commande::whereNotNull('user_id')
            ->selectRaw('statut, COUNT(*) as count')
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        $this->info('');
        $this->info('📋 ORDERS WITH AFFILIATES');
        $this->table(
            ['Status', 'Count'],
            collect($orderCounts)->map(function ($count, $status) {
                return [$status, $count];
            })->toArray()
        );

        // Count affiliates
        $affiliateCount = User::role('affiliate')->count();
        $this->info("👥 Test Affiliates Created: {$affiliateCount}");
    }

    /**
     * Display next steps for testing
     */
    private function displayNextSteps(): void
    {
        $this->info('');
        $this->info('🎯 NEXT STEPS FOR TESTING');
        $this->info('=========================');
        
        $this->info('1. 🌐 Open Admin Panel:');
        $this->line('   → Navigate to: http://localhost:5174/admin/commissions');
        
        $this->info('');
        $this->info('2. 🧪 Test Commission Management:');
        $this->line('   → View commission list with filters');
        $this->line('   → Test approve/reject/adjust actions');
        $this->line('   → Try bulk operations');
        $this->line('   → Export commission data');
        
        $this->info('');
        $this->info('3. 📊 Test Commission Detail Pages:');
        $this->line('   → Click view (eye) icon to see commission details');
        $this->line('   → Test individual approve/reject/adjust actions');
        
        $this->info('');
        $this->info('4. 🔄 Test Order Status Changes:');
        $this->line('   → Go to Pre-orders page');
        $this->line('   → Change order status to "livree" to trigger commissions');
        $this->line('   → Change to "retournee" to test return policy');
        
        $this->info('');
        $this->info('5. ⚙️  Test Commission Settings:');
        $this->line('   → Modify settings in app_settings table:');
        $this->line('     • commission.trigger_status');
        $this->line('     • commission.cooldown_days');
        $this->line('     • commission.default_rate');
        
        $this->info('');
        $this->info('6. 🤖 Test Automated Processing:');
        $this->line('   → Run: php artisan commissions:process-eligible');
        $this->line('   → Run: php artisan commissions:test-calculation');
        
        $this->info('');
        $this->info('💡 TIP: Use different affiliate logins to test affiliate-side views (when implemented)');
        
        $this->info('');
        $this->warn('⚠️  Remember: Commissions are created automatically when orders reach trigger status!');
        $this->warn('   No manual commission creation is needed - it\'s all automated! 🚀');
    }
}
