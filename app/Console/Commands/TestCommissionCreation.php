<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Commande;
use App\Events\OrderDelivered;
use App\Services\CommissionService;

class TestCommissionCreation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:commission-creation {order_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test commission creation for an order';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = $this->argument('order_id');

        if (!$orderId) {
            // Find a random order with affiliate but no commissions
            $order = Commande::whereNotNull('user_id')
                ->whereHas('affiliate', function($q) {
                    $q->whereHas('roles', function($r) {
                        $r->where('name', 'affiliate');
                    });
                })
                ->whereDoesntHave('commissions')
                ->with(['affiliate', 'articles.produit', 'commissions'])
                ->first();

            if (!$order) {
                $this->error('No orders with affiliates found');
                return 1;
            }
        } else {
            $order = Commande::with(['affiliate', 'articles.produit', 'commissions'])->find($orderId);
            if (!$order) {
                $this->error("Order {$orderId} not found");
                return 1;
            }
        }

        $this->info("Testing commission creation for order: {$order->id}");
        $this->info("Order status: {$order->statut}");
        $this->info("Affiliate: {$order->affiliate?->nom_complet} ({$order->affiliate?->email})");
        $this->info("Articles count: " . $order->articles->count());

        $existingCommissions = $order->commissions->count();
        $this->info("Existing commissions: {$existingCommissions}");

        // Test direct commission service
        $this->info("\n--- Testing CommissionService directly ---");
        $commissionService = app(CommissionService::class);
        $result = $commissionService->calculateForOrder($order);

        $this->info("Commission calculation result:");
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

        // Test event dispatch
        $this->info("\n--- Testing OrderDelivered event ---");
        OrderDelivered::dispatch($order, 'test_command', [
            'test' => true,
            'command_run_at' => now()->toISOString()
        ]);

        $this->info("OrderDelivered event dispatched");

        // Check commissions after event
        $order->refresh();
        $newCommissions = $order->commissions->count();
        $this->info("Commissions after event: {$newCommissions}");

        if ($newCommissions > $existingCommissions) {
            $this->info("✅ Commission creation successful!");

            $this->info("\nCommission details:");
            foreach ($order->commissions as $commission) {
                $this->line("- ID: {$commission->id}, Amount: {$commission->amount} {$commission->currency}, Status: {$commission->status}");
            }
        } else {
            $this->warn("⚠️  No new commissions created");
        }

        return 0;
    }
}
