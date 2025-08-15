<?php

namespace App\Console\Commands;

use App\Models\ShippingParcel;
use Illuminate\Console\Command;

class CheckParcelsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parcels:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of shipping parcels (real vs mock)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('📦 Checking Shipping Parcels Status...');
        $this->line('');

        // Count total parcels
        $total = ShippingParcel::count();
        
        // Count mock parcels
        $mock = ShippingParcel::whereJsonContains('meta->mock_data', true)->count();
        
        // Count real parcels
        $real = $total - $mock;

        // Display summary
        $this->line("📊 SUMMARY:");
        $this->line("Total parcels: {$total}");
        $this->line("Mock parcels: {$mock}");
        $this->line("Real parcels: {$real}");
        $this->line('');

        if ($real > 0) {
            $this->info('✅ You have REAL parcels from OzonExpress API!');
            
            // Show recent real parcels
            $realParcels = ShippingParcel::where(function($query) {
                $query->whereJsonDoesntContain('meta->mock_data', true)
                      ->orWhereNull('meta->mock_data');
            })
            ->latest()
            ->limit(5)
            ->get();

            if ($realParcels->count() > 0) {
                $this->line('');
                $this->line('🚀 Recent REAL parcels:');
                foreach ($realParcels as $parcel) {
                    $this->line("  - {$parcel->tracking_number} (Created: {$parcel->created_at->format('Y-m-d H:i')})");
                }
            }
        } else {
            $this->warn('⚠️  No real parcels found. All parcels are mock data.');
            $this->line('');
            $this->line('To create real parcels:');
            $this->line('1. Go to Admin > Orders > Pre-Orders');
            $this->line('2. Select confirmed orders');
            $this->line('3. Click "Envoyer OzonExpress"');
        }

        if ($mock > 0) {
            $this->line('');
            $this->line('🔧 Recent MOCK parcels:');
            $mockParcels = ShippingParcel::whereJsonContains('meta->mock_data', true)
                ->latest()
                ->limit(3)
                ->get();

            foreach ($mockParcels as $parcel) {
                $this->line("  - {$parcel->tracking_number} (Mock - Created: {$parcel->created_at->format('Y-m-d H:i')})");
            }
        }

        $this->line('');
        $this->info('💡 Use the debug page to see more details: /admin/debug/ozonexpress');

        return 0;
    }
}
