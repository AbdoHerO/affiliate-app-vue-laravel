<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use App\Models\CommissionAffilie;
use Illuminate\Console\Command;

class CheckWithdrawalData extends Command
{
    protected $signature = 'affiliate:check-withdrawal-data';
    protected $description = 'Check withdrawal and commission data integrity';

    public function handle()
    {
        $this->info("=== Withdrawal Data Analysis ===");
        
        // Check total counts
        $withdrawalCount = Withdrawal::count();
        $itemCount = WithdrawalItem::count();
        $commissionCount = CommissionAffilie::count();
        
        $this->info("Total Withdrawals: {$withdrawalCount}");
        $this->info("Total Withdrawal Items: {$itemCount}");
        $this->info("Total Commissions: {$commissionCount}");
        
        // Check sample withdrawal
        $withdrawal = Withdrawal::with('items')->first();
        if ($withdrawal) {
            $this->info("\n=== Sample Withdrawal Analysis ===");
            $this->info("Withdrawal ID: {$withdrawal->id}");
            $this->info("Amount: {$withdrawal->amount}");
            $this->info("Status: {$withdrawal->status}");
            $this->info("Items Count: {$withdrawal->items->count()}");
            $this->info("Commission Count (accessor): {$withdrawal->commission_count}");
            
            if ($withdrawal->items->count() > 0) {
                $this->info("\n--- Withdrawal Items ---");
                foreach ($withdrawal->items as $item) {
                    $this->info("Item ID: {$item->id}, Commission ID: {$item->commission_id}, Amount: {$item->amount}");
                }
            } else {
                $this->warn("⚠️ This withdrawal has no items!");
            }
        }
        
        // Check commissions with paid_withdrawal_id
        $paidCommissions = CommissionAffilie::whereNotNull('paid_withdrawal_id')->count();
        $this->info("\nCommissions with paid_withdrawal_id: {$paidCommissions}");
        
        // Check for orphaned withdrawal items
        $orphanedItems = WithdrawalItem::whereDoesntHave('commission')->count();
        $this->info("Orphaned withdrawal items (no commission): {$orphanedItems}");
        
        // Check for withdrawals without items
        $emptyWithdrawals = Withdrawal::whereDoesntHave('items')->count();
        $this->info("Withdrawals without items: {$emptyWithdrawals}");
        
        if ($emptyWithdrawals > 0) {
            $this->warn("⚠️ Found {$emptyWithdrawals} withdrawals without items!");
            $emptyWithdrawalIds = Withdrawal::whereDoesntHave('items')->pluck('id');
            $this->info("Empty withdrawal IDs: " . $emptyWithdrawalIds->implode(', '));
        }
        
        return 0;
    }
}
