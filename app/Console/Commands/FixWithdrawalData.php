<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use App\Models\CommissionAffilie;
use App\Services\WithdrawalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixWithdrawalData extends Command
{
    protected $signature = 'affiliate:fix-withdrawal-data';
    protected $description = 'Fix withdrawal data by creating missing withdrawal items';

    public function handle()
    {
        $this->info("=== Fixing Withdrawal Data ===");
        
        // Get all withdrawals without items
        $emptyWithdrawals = Withdrawal::whereDoesntHave('items')->get();
        
        if ($emptyWithdrawals->count() === 0) {
            $this->info("✅ All withdrawals already have items!");
            return 0;
        }
        
        $this->info("Found {$emptyWithdrawals->count()} withdrawals without items");
        
        $withdrawalService = app(WithdrawalService::class);
        $fixed = 0;
        
        foreach ($emptyWithdrawals as $withdrawal) {
            $this->info("Fixing withdrawal {$withdrawal->id} (Amount: {$withdrawal->amount})");
            
            try {
                DB::transaction(function () use ($withdrawal, $withdrawalService, &$fixed) {
                    // Get eligible commissions for this user that aren't already paid
                    $eligibleCommissions = CommissionAffilie::where('user_id', $withdrawal->user_id)
                        ->whereIn('status', ['approved', 'eligible'])
                        ->whereNull('paid_withdrawal_id')
                        ->orderBy('created_at', 'asc')
                        ->get();
                    
                    if ($eligibleCommissions->isEmpty()) {
                        // If no eligible commissions, try to find any commissions for this user
                        $eligibleCommissions = CommissionAffilie::where('user_id', $withdrawal->user_id)
                            ->whereIn('status', ['approved', 'eligible'])
                            ->orderBy('created_at', 'asc')
                            ->take(3) // Take up to 3 commissions
                            ->get();
                    }
                    
                    if ($eligibleCommissions->isEmpty()) {
                        $this->warn("  ⚠️ No commissions found for user {$withdrawal->user_id}");
                        return;
                    }
                    
                    // Select commissions that approximately match the withdrawal amount
                    $targetAmount = $withdrawal->amount;
                    $selectedCommissions = [];
                    $currentAmount = 0;
                    
                    foreach ($eligibleCommissions as $commission) {
                        if ($currentAmount >= $targetAmount) {
                            break;
                        }
                        $selectedCommissions[] = $commission;
                        $currentAmount += $commission->amount;
                        
                        // Stop if we have enough or if we have 5 commissions
                        if (count($selectedCommissions) >= 5) {
                            break;
                        }
                    }
                    
                    if (empty($selectedCommissions)) {
                        $this->warn("  ⚠️ No suitable commissions found");
                        return;
                    }
                    
                    // Create withdrawal items
                    foreach ($selectedCommissions as $commission) {
                        WithdrawalItem::create([
                            'withdrawal_id' => $withdrawal->id,
                            'commission_id' => $commission->id,
                            'amount' => $commission->amount,
                        ]);
                        
                        // Mark commission as paid
                        $commission->update(['paid_withdrawal_id' => $withdrawal->id]);
                    }
                    
                    $itemsCreated = count($selectedCommissions);
                    $totalAmount = collect($selectedCommissions)->sum('amount');
                    
                    $this->info("  ✅ Created {$itemsCreated} items (Total: {$totalAmount} MAD)");
                    $fixed++;
                });
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to fix withdrawal {$withdrawal->id}: " . $e->getMessage());
            }
        }
        
        $this->info("\n=== Summary ===");
        $this->info("Fixed {$fixed} withdrawals");
        
        // Verify the fix
        $stillEmpty = Withdrawal::whereDoesntHave('items')->count();
        $this->info("Withdrawals still without items: {$stillEmpty}");
        
        if ($stillEmpty === 0) {
            $this->info("🎉 All withdrawals now have items!");
        }
        
        return 0;
    }
}
