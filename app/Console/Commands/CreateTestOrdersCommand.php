<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\TestOrdersSeeder;
use App\Models\Commande;
use App\Models\ShippingParcel;
use App\Services\OzonExpressService;

class CreateTestOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:create-test 
                            {--count=5 : Number of test orders to create}
                            {--track : Also track all created parcels after creation}
                            {--status : Show current shipping status}';

    /**
     * The console command description.
     */
    protected $description = 'Create test orders and send them to OzonExpress for shipping tracking testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('status')) {
            $this->showShippingStatus();
            return;
        }

        $this->info('🚀 Creating test orders for OzonExpress shipping...');
        $this->newLine();

        // Run the seeder
        $seeder = new TestOrdersSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        if ($this->option('track')) {
            $this->newLine();
            $this->info('🔍 Tracking all created parcels...');
            $this->trackAllParcels();
        }

        $this->newLine();
        $this->info('✅ Test orders creation completed!');
        $this->info('💡 You can now check the shipping orders page to see real tracking data.');
    }

    /**
     * Track all parcels to get latest status
     */
    private function trackAllParcels()
    {
        $ozonService = app(OzonExpressService::class);
        
        $parcels = ShippingParcel::where('provider', 'ozonexpress')
            ->whereNotNull('tracking_number')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        if ($parcels->isEmpty()) {
            $this->warn('No OzonExpress parcels found to track.');
            return;
        }

        $this->info("Found {$parcels->count()} parcels to track...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($parcels->count());
        $progressBar->start();

        foreach ($parcels as $parcel) {
            try {
                $result = $ozonService->track($parcel->tracking_number);
                
                if ($result['success']) {
                    $this->newLine();
                    $this->info("✅ {$parcel->tracking_number} - Status: {$parcel->fresh()->status}");
                } else {
                    $this->newLine();
                    $this->error("❌ {$parcel->tracking_number} - Error: {$result['message']}");
                }
                
                $progressBar->advance();
                sleep(1); // Rate limiting
                
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("❌ {$parcel->tracking_number} - Exception: {$e->getMessage()}");
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);
    }

    /**
     * Show current shipping status
     */
    private function showShippingStatus()
    {
        $this->info('📊 CURRENT SHIPPING STATUS');
        $this->info(str_repeat('=', 50));

        // Get statistics
        $totalOrders = Commande::where('statut', 'confirmee')->count();
        $shippedOrders = Commande::whereHas('shippingParcel')->count();
        $pendingOrders = $totalOrders - $shippedOrders;

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Confirmed Orders', $totalOrders],
                ['Orders with Shipping', $shippedOrders],
                ['Pending Shipping', $pendingOrders],
            ]
        );

        // Recent parcels
        $recentParcels = ShippingParcel::with('commande')
            ->where('provider', 'ozonexpress')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        if ($recentParcels->isNotEmpty()) {
            $this->newLine();
            $this->info('📦 RECENT PARCELS');
            $this->info(str_repeat('-', 50));

            $tableData = [];
            foreach ($recentParcels as $parcel) {
                $tableData[] = [
                    $parcel->tracking_number,
                    $parcel->commande->code ?? 'N/A',
                    $parcel->receiver ?? 'N/A',
                    $parcel->status,
                    $parcel->last_status_text ?? 'N/A',
                    $parcel->created_at->format('Y-m-d H:i'),
                ];
            }

            $this->table(
                ['Tracking', 'Order', 'Receiver', 'Status', 'Last Status', 'Created'],
                $tableData
            );
        }

        $this->newLine();
        $this->info('💡 Commands:');
        $this->info('  php artisan orders:create-test --count=3    # Create 3 test orders');
        $this->info('  php artisan orders:create-test --track      # Create orders and track them');
        $this->info('  php artisan orders:create-test --status     # Show this status');
    }
}
