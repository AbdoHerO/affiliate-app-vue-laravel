<?php

namespace App\Console\Commands;

use App\Models\CommissionAffilie;
use Illuminate\Console\Command;

class CheckCommissionData extends Command
{
    protected $signature = 'commission:check-data';
    protected $description = 'Check commission data for NaN issues';

    public function handle()
    {
        $this->info('🔍 Checking commission data...');
        
        // Get a sample of commissions
        $commissions = CommissionAffilie::with(['commande', 'commandeArticle.produit'])
            ->take(5)
            ->get();
        
        if ($commissions->isEmpty()) {
            $this->warn('No commissions found');
            return;
        }
        
        foreach ($commissions as $commission) {
            $this->info("Commission ID: {$commission->id}");
            $this->info("  Base Amount: " . ($commission->base_amount ?? 'NULL'));
            $this->info("  Rate: " . ($commission->rate ?? 'NULL'));
            $this->info("  Amount: " . ($commission->amount ?? 'NULL'));
            $this->info("  Order ID: " . ($commission->commande_id ?? 'NULL'));
            $this->info("  Order exists: " . ($commission->commande ? 'YES' : 'NO'));
            if ($commission->commande) {
                $this->info("  Order ref: #{$commission->commande->id}");
            }
            if ($commission->commandeArticle && $commission->commandeArticle->produit) {
                $this->info("  Product: {$commission->commandeArticle->produit->titre}");
            } else {
                $this->info("  Product: N/A");
            }
            $this->info("  Status: {$commission->status}");
            $this->info("---");
        }
        
        // Check for NULL values
        $nullBaseAmount = CommissionAffilie::whereNull('base_amount')->count();
        $nullRate = CommissionAffilie::whereNull('rate')->count();
        $nullAmount = CommissionAffilie::whereNull('amount')->count();
        $nullOrderId = CommissionAffilie::whereNull('commande_id')->count();
        
        $this->info("\n📊 Data Quality Summary:");
        $this->info("NULL base_amount: {$nullBaseAmount}");
        $this->info("NULL rate: {$nullRate}");
        $this->info("NULL amount: {$nullAmount}");
        $this->info("NULL commande_id: {$nullOrderId}");
        
        return 0;
    }
}
