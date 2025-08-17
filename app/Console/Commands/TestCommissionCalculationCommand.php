<?php

namespace App\Console\Commands;

use App\Models\Commande;
use App\Services\CommissionService;
use Illuminate\Console\Command;

class TestCommissionCalculationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commissions:test-calculation {order_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test commission calculation for an order';

    /**
     * Execute the console command.
     */
    public function handle(CommissionService $commissionService)
    {
        $orderId = $this->argument('order_id');

        if (!$orderId) {
            // Get a random order with an affiliate
            $order = Commande::whereNotNull('user_id')
                ->with(['articles', 'affiliate'])
                ->first();

            if (!$order) {
                $this->error('No orders with affiliates found.');
                return 1;
            }
        } else {
            $order = Commande::with(['articles', 'affiliate'])->find($orderId);
            if (!$order) {
                $this->error("Order {$orderId} not found.");
                return 1;
            }
        }

        $this->info("Testing commission calculation for order: {$order->id}");
        $this->info("Order status: {$order->statut}");
        $this->info("Affiliate: " . ($order->affiliate?->nom_complet ?? 'None'));

        if (!$order->user_id) {
            $this->warn('Order has no affiliate assigned.');
            return 1;
        }

        try {
            $result = $commissionService->calculateForOrder($order);

            if ($result['success']) {
                $this->info("✅ Commission calculation successful!");
                $this->info("Total commission amount: {$result['total_amount']} MAD");
                $this->info("Number of commission items: " . count($result['commissions']));

                $this->table(
                    ['Commission ID', 'Article', 'Base Amount', 'Rate', 'Amount', 'Status', 'Rule'],
                    collect($result['commissions'])->map(function ($commission) {
                        return [
                            $commission->id,
                            $commission->commandeArticle?->produit?->titre ?? 'N/A',
                            $commission->base_amount,
                            $commission->rate ? $commission->rate . '%' : 'Fixed',
                            $commission->amount,
                            $commission->status,
                            $commission->rule_code,
                        ];
                    })->toArray()
                );
            } else {
                $this->error("❌ Commission calculation failed: {$result['message']}");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Exception during commission calculation: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
