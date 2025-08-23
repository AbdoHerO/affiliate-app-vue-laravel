<?php

namespace App\Console\Commands;

use App\Models\ShippingParcel;
use App\Services\OzonExpressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TrackOzonExpressParcels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ozonexpress:track-parcels 
                            {--limit=50 : Maximum number of parcels to track in one run}
                            {--force : Track even if recently synced}
                            {--tracking= : Track specific tracking number only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically track OzonExpress parcels and detect deliveries for commission creation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚚 Starting OzonExpress Parcel Tracking...');
        
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $specificTracking = $this->option('tracking');
        
        // Get OzonExpress service
        $ozonService = app(OzonExpressService::class);
        
        if (!$ozonService->isEnabled()) {
            $this->warn('⚠️ OzonExpress service is disabled. Skipping tracking.');
            return 0;
        }
        
        // Build query for parcels to track
        $query = ShippingParcel::where('provider', 'ozonexpress');
        
        if ($specificTracking) {
            $query->where('tracking_number', $specificTracking);
            $this->info("🎯 Tracking specific parcel: {$specificTracking}");
        } else {
            // Only track parcels that are not in final states
            $query->whereNotIn('status', ['delivered', 'returned', 'cancelled', 'refused']);
            
            if (!$force) {
                // Skip recently synced parcels (within last 30 minutes)
                $query->where(function ($q) {
                    $q->whereNull('last_synced_at')
                      ->orWhere('last_synced_at', '<', now()->subMinutes(30));
                });
            }
            
            $query->orderBy('last_synced_at', 'asc')
                  ->limit($limit);
        }
        
        $parcels = $query->get();
        
        if ($parcels->isEmpty()) {
            $this->info('✅ No parcels need tracking at this time.');
            return 0;
        }
        
        $this->info("📦 Found {$parcels->count()} parcels to track");
        
        $stats = [
            'tracked' => 0,
            'delivered' => 0,
            'status_changed' => 0,
            'errors' => 0,
            'commissions_created' => 0,
        ];
        
        $progressBar = $this->output->createProgressBar($parcels->count());
        $progressBar->start();
        
        foreach ($parcels as $parcel) {
            try {
                $oldStatus = $parcel->status;
                $oldOrderStatus = $parcel->commande?->statut;
                
                // Track the parcel
                $result = $ozonService->track($parcel->tracking_number);
                
                if ($result['success']) {
                    $stats['tracked']++;
                    
                    // Refresh parcel to get updated status
                    $parcel->refresh();
                    $newStatus = $parcel->status;
                    $newOrderStatus = $parcel->commande?->statut;
                    
                    // Check if status changed
                    if ($oldStatus !== $newStatus) {
                        $stats['status_changed']++;
                        
                        $this->newLine();
                        $this->info("📊 Status Update: {$parcel->tracking_number}");
                        $this->line("   Old: {$oldStatus} → New: {$newStatus}");
                        
                        // Check if delivered
                        if ($newStatus === 'delivered') {
                            $stats['delivered']++;
                            
                            $this->line("   🎉 DELIVERED! Commission will be created automatically.");
                            
                            // Check if commission was created
                            if ($parcel->commande) {
                                $commissionsCount = $parcel->commande->commissions()->count();
                                if ($commissionsCount > 0) {
                                    $stats['commissions_created']++;
                                    $this->line("   💰 Commission created successfully!");
                                }
                            }
                        }
                    }
                } else {
                    $stats['errors']++;
                    $this->newLine();
                    $this->error("❌ Failed to track {$parcel->tracking_number}: {$result['message']}");
                }
                
                $progressBar->advance();
                
                // Small delay to avoid overwhelming the API
                usleep(100000); // 0.1 second
                
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->newLine();
                $this->error("❌ Exception tracking {$parcel->tracking_number}: {$e->getMessage()}");
                
                Log::error('OzonExpress tracking failed', [
                    'tracking_number' => $parcel->tracking_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $progressBar->advance();
            }
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Display summary
        $this->info('📊 Tracking Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Parcels Tracked', $stats['tracked']],
                ['Status Changes', $stats['status_changed']],
                ['Deliveries Detected', $stats['delivered']],
                ['Commissions Created', $stats['commissions_created']],
                ['Errors', $stats['errors']],
            ]
        );
        
        // Log summary for monitoring
        Log::info('OzonExpress tracking completed', [
            'parcels_processed' => $parcels->count(),
            'stats' => $stats,
            'command_options' => [
                'limit' => $limit,
                'force' => $force,
                'specific_tracking' => $specificTracking,
            ],
        ]);
        
        if ($stats['delivered'] > 0) {
            $this->info("🎉 {$stats['delivered']} deliveries detected! Commissions will be created automatically.");
        }
        
        if ($stats['errors'] > 0) {
            $this->warn("⚠️ {$stats['errors']} errors occurred. Check logs for details.");
            return 1;
        }
        
        $this->info('✅ OzonExpress tracking completed successfully!');
        return 0;
    }
}
