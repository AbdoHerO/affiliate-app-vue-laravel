<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class CleanupExpiredReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:cleanup
                            {--dry-run : Show what would be cleaned up without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired stock reservations';

    protected ReservationService $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        parent::__construct();
        $this->reservationService = $reservationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting expired reservations cleanup...');

        try {
            if ($this->option('dry-run')) {
                $this->info('DRY RUN MODE - No changes will be made');
                
                $expiredCount = \App\Models\ReservationStock::where('statut', 'active')
                    ->where('date_expire', '<', now())
                    ->count();
                    
                $this->info("Found {$expiredCount} expired reservations that would be cleaned up.");
                
                if ($expiredCount > 0) {
                    $this->table(
                        ['ID', 'Product', 'Quantity', 'Expired At'],
                        \App\Models\ReservationStock::where('statut', 'active')
                            ->where('date_expire', '<', now())
                            ->with('variante.produit')
                            ->get()
                            ->map(function ($reservation) {
                                return [
                                    $reservation->id,
                                    $reservation->variante->produit->titre ?? 'N/A',
                                    $reservation->quantite,
                                    $reservation->date_expire?->format('Y-m-d H:i:s') ?? 'N/A'
                                ];
                            })
                            ->toArray()
                    );
                }
            } else {
                $cleanedCount = $this->reservationService->cleanupExpiredReservations();
                
                if ($cleanedCount > 0) {
                    $this->info("Successfully cleaned up {$cleanedCount} expired reservations.");
                } else {
                    $this->info('No expired reservations found.');
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error during cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
