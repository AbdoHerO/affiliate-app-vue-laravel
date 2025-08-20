<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Commande;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class BackfillClientFinalCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:backfill-client-final 
                            {--chunk=100 : Number of orders to process per chunk}
                            {--dry-run : Show what would be updated without making changes}
                            {--force : Force update even if snapshot already exists}';

    /**
     * The console command description.
     */
    protected $description = 'Backfill client_final_snapshot for existing orders from client/address relationships';

    /**
     * Execute the console command.
     */
    public function handle(OrderService $orderService)
    {
        $chunkSize = (int) $this->option('chunk');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🔄 Starting client final backfill process...');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Build query
        $query = Commande::with(['client', 'adresse', 'clientFinal']);
        
        if (!$force) {
            $query->whereNull('client_final_snapshot');
        }

        $totalOrders = $query->count();
        
        if ($totalOrders === 0) {
            $this->info('✅ No orders need backfilling.');
            return 0;
        }

        $this->info("📊 Found {$totalOrders} orders to process");
        $this->newLine();

        $processed = 0;
        $updated = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($totalOrders);
        $progressBar->start();

        $query->chunkById($chunkSize, function ($orders) use ($orderService, $dryRun, &$processed, &$updated, &$errors, $progressBar) {
            foreach ($orders as $order) {
                $processed++;
                
                try {
                    // Get client and address data
                    $client = $order->client;
                    $address = $order->adresse;
                    
                    if (!$client || !$address) {
                        $errors++;
                        $this->newLine();
                        $this->error("❌ Order {$order->id}: Missing client or address data");
                        continue;
                    }

                    // Look up city ID from shipping cities if available
                    $shippingCity = DB::table('shipping_cities')
                        ->where('provider', 'ozonexpress')
                        ->where('name', $address->ville)
                        ->where('active', true)
                        ->first();

                    // Create client final snapshot
                    $snapshot = [
                        'nom_complet' => $client->nom_complet,
                        'telephone' => $client->telephone,
                        'email' => $client->email,
                        'adresse' => $address->adresse,
                        'ville' => $address->ville,
                        'ville_id' => $shippingCity ? $shippingCity->city_id : null,
                        'code_postal' => $address->code_postal,
                        'pays' => $address->pays ?? 'MA'
                    ];

                    if (!$dryRun) {
                        // Update the order
                        $order->update([
                            'client_final_id' => $order->client_id,
                            'client_final_snapshot' => $snapshot
                        ]);
                    }

                    $updated++;

                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("❌ Order {$order->id}: {$e->getMessage()}");
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();
        $this->newLine();

        // Summary
        $this->info('📈 BACKFILL SUMMARY');
        $this->info(str_repeat('=', 50));
        
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $processed],
                ['Successfully Updated', $updated],
                ['Errors', $errors],
                ['Success Rate', $processed > 0 ? round(($updated / $processed) * 100, 2) . '%' : '0%']
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->info('💡 Run without --dry-run to apply changes');
        } else {
            $this->newLine();
            $this->info('✅ Backfill completed successfully!');
        }

        return $errors > 0 ? 1 : 0;
    }
}
