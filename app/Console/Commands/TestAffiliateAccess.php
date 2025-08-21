<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Models\Ticket;
use Illuminate\Console\Command;

class TestAffiliateAccess extends Command
{
    protected $signature = 'affiliate:test-access {user_id}';
    protected $description = 'Test affiliate access and data counts';

    public function handle()
    {
        $userId = $this->argument('user_id');
        
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }
        
        $this->info("Testing affiliate access for: {$user->nom_complet}");
        $this->info("Email: {$user->email}");
        $this->info("Approval Status: {$user->approval_status}");
        $this->info("Has affiliate role: " . ($user->hasRole('affiliate') ? 'Yes' : 'No'));
        $this->info("Is approved affiliate: " . ($user->isApprovedAffiliate() ? 'Yes' : 'No'));
        
        if (!$user->isApprovedAffiliate()) {
            $this->error("User is not an approved affiliate!");
            return 1;
        }
        
        // Test data counts
        $ordersCount = Commande::where('user_id', $user->id)->count();
        $commissionsCount = CommissionAffilie::where('user_id', $user->id)->count();
        $withdrawalsCount = Withdrawal::where('user_id', $user->id)->count();
        $ticketsCount = Ticket::where('requester_id', $user->id)->count();
        
        $this->info("\n--- Data Counts ---");
        $this->info("Orders: {$ordersCount}");
        $this->info("Commissions: {$commissionsCount}");
        $this->info("Withdrawals: {$withdrawalsCount}");
        $this->info("Tickets: {$ticketsCount}");
        
        // Test commission status breakdown
        $commissionsByStatus = CommissionAffilie::where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('status')
            ->get();
            
        $this->info("\n--- Commission Breakdown ---");
        foreach ($commissionsByStatus as $status) {
            $this->info("{$status->status}: {$status->count} commissions, {$status->total} MAD");
        }
        
        $this->info("\n✅ Affiliate access test completed successfully!");
        
        return 0;
    }
}
