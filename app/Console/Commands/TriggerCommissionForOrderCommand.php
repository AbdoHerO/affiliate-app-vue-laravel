<?php

namespace App\Console\Commands;

use App\Models\Commande;
use App\Services\CommissionService;
use Illuminate\Console\Command;

class TriggerCommissionForOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commissions:trigger {order_id : Order ID to trigger commission calculation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually trigger commission calculation for a specific order';

    /**
     * Execute the console command.
     */
    public function handle(CommissionService $commissionService)
    {
        $orderId = $this->argument('order_id');
        
        $this->info("🎯 Triggering commission calculation for order: {$orderId}");
        
        $order = Commande::with(['articles.produit', 'user'])->find($orderId);
        
        if (!$order) {
            $this->error("❌ Order not found: {$orderId}");
            return 1;
        }
        
        $this->info("📋 Order Details:");
        $this->info("   Status: {$order->statut}");
        $this->info("   Affiliate: " . ($order->user ? $order->user->nom_complet : 'None'));
        $this->info("   Total: {$order->total_ttc} {$order->devise}");
        $this->info("   Articles: " . $order->articles->count());
        
        if (!$order->user_id) {
            $this->error("❌ Order has no affiliate (user_id is null)");
            $this->info("💡 Only orders with affiliates can generate commissions");
            return 1;
        }
        
        if ($order->articles->isEmpty()) {
            $this->error("❌ Order has no articles");
            return 1;
        }
        
        try {
            $this->info("\n🔄 Calculating commissions...");
            $result = $commissionService->calculateForOrder($order);
            
            if ($result['success']) {
                $this->info("✅ Commission calculation successful!");
                $this->info("   Total Amount: {$result['total_amount']} MAD");
                $this->info("   Commissions Created: {$result['commissions_count']}");
                
                $this->info("\n📊 Commission Details:");
                foreach ($result['commissions'] as $commission) {
                    $this->info("   • {$commission->produit->titre}: {$commission->amount} MAD ({$commission->status})");
                }
                
                $this->info("\n🌐 View in admin panel:");
                $this->info("   http://localhost:5174/admin/commissions");
                
            } else {
                $this->error("❌ Commission calculation failed: {$result['message']}");
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Exception during calculation: {$e->getMessage()}");
            $this->error("   Stack trace: {$e->getTraceAsString()}");
            return 1;
        }
        
        return 0;
    }
}
