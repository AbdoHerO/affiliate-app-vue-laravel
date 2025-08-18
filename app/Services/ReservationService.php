<?php

namespace App\Services;

use App\Models\ReservationStock;
use App\Models\Stock;
use App\Models\ProduitVariante;
use App\Models\Entrepot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReservationService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Create a new stock reservation
     */
    public function createReservation(
        string $varianteId,
        string $entrepotId,
        int $quantity,
        array $options = []
    ): ReservationStock {
        return DB::transaction(function () use ($varianteId, $entrepotId, $quantity, $options) {
            // Validate inputs
            if ($quantity <= 0) {
                throw new \InvalidArgumentException('Quantity must be positive');
            }

            // Check if variant and warehouse exist
            $variant = ProduitVariante::findOrFail($varianteId);
            $entrepot = Entrepot::findOrFail($entrepotId);

            // Check available stock (excluding current reservations)
            $available = $this->stockService->available($varianteId, $entrepotId);
            if ($available < $quantity) {
                throw new \Exception("Insufficient available stock. Available: {$available}, Requested: {$quantity}");
            }

            // Create reservation record
            $reservation = ReservationStock::create([
                'variante_id' => $varianteId,
                'entrepot_id' => $entrepotId,
                'quantite' => $quantity,
                'gamme_id' => $options['gamme_id'] ?? null,
                'affilie_id' => $options['affilie_id'] ?? null,
                'offre_id' => $options['offre_id'] ?? null,
                'date_expire' => $options['expire_at'] ?? now()->addDays(7),
                'statut' => 'active'
            ]);

            // Update stock reserved quantity
            $this->updateStockReserved($varianteId, $entrepotId);

            Log::info('Stock reservation created', [
                'reservation_id' => $reservation->id,
                'variante_id' => $varianteId,
                'entrepot_id' => $entrepotId,
                'quantity' => $quantity,
                'options' => $options
            ]);

            return $reservation;
        });
    }

    /**
     * Release a reservation
     */
    public function releaseReservation(string $reservationId, string $reason = 'manual'): bool
    {
        return DB::transaction(function () use ($reservationId, $reason) {
            $reservation = ReservationStock::findOrFail($reservationId);

            if ($reservation->statut !== 'active') {
                throw new \Exception('Reservation is not active');
            }

            // Update reservation status
            $reservation->update(['statut' => 'annulee']);

            // Update stock reserved quantity
            $this->updateStockReserved($reservation->variante_id, $reservation->entrepot_id);

            Log::info('Stock reservation released', [
                'reservation_id' => $reservationId,
                'reason' => $reason,
                'quantity' => $reservation->quantite
            ]);

            return true;
        });
    }

    /**
     * Use a reservation (convert to actual stock movement)
     */
    public function useReservation(string $reservationId, ?string $reference = null): bool
    {
        return DB::transaction(function () use ($reservationId, $reference) {
            $reservation = ReservationStock::findOrFail($reservationId);

            if ($reservation->statut !== 'active') {
                throw new \Exception('Reservation is not active');
            }

            // Create stock movement (out)
            $this->stockService->move(
                $reservation->variante_id,
                $reservation->entrepot_id,
                'out',
                $reservation->quantite,
                'delivery_return',
                'Used reservation: ' . $reservationId,
                $reference
            );

            // Update reservation status
            $reservation->update(['statut' => 'utilisee']);

            // Update stock reserved quantity
            $this->updateStockReserved($reservation->variante_id, $reservation->entrepot_id);

            Log::info('Stock reservation used', [
                'reservation_id' => $reservationId,
                'quantity' => $reservation->quantite,
                'reference' => $reference
            ]);

            return true;
        });
    }

    /**
     * Update the reserved quantity in the stocks table based on active reservations
     */
    protected function updateStockReserved(string $varianteId, string $entrepotId): void
    {
        $totalReserved = ReservationStock::where('variante_id', $varianteId)
            ->where('entrepot_id', $entrepotId)
            ->where('statut', 'active')
            ->sum('quantite');

        Stock::updateOrCreate(
            ['variante_id' => $varianteId, 'entrepot_id' => $entrepotId],
            ['qte_reservee' => $totalReserved]
        );

        Log::debug('Stock reserved quantity updated', [
            'variante_id' => $varianteId,
            'entrepot_id' => $entrepotId,
            'total_reserved' => $totalReserved
        ]);
    }

    /**
     * Clean up expired reservations
     */
    public function cleanupExpiredReservations(): int
    {
        $expired = ReservationStock::where('statut', 'active')
            ->where('date_expire', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $reservation) {
            $reservation->update(['statut' => 'expiree']);
            $this->updateStockReserved($reservation->variante_id, $reservation->entrepot_id);
            $count++;
        }

        if ($count > 0) {
            Log::info('Expired reservations cleaned up', ['count' => $count]);
        }

        return $count;
    }

    /**
     * Get reservations for a product variant
     */
    public function getReservations(string $varianteId, ?string $entrepotId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = ReservationStock::where('variante_id', $varianteId)
            ->with(['variante.produit', 'entrepot', 'affilie', 'offre']);

        if ($entrepotId) {
            $query->where('entrepot_id', $entrepotId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get reservation statistics
     */
    public function getReservationStats(): array
    {
        return [
            'total_active' => ReservationStock::where('statut', 'active')->count(),
            'total_expired' => ReservationStock::where('statut', 'expiree')->count(),
            'total_used' => ReservationStock::where('statut', 'utilisee')->count(),
            'total_cancelled' => ReservationStock::where('statut', 'annulee')->count(),
            'total_quantity_reserved' => ReservationStock::where('statut', 'active')->sum('quantite'),
        ];
    }

    /**
     * Create reservations for an order
     */
    public function createOrderReservations(\App\Models\Commande $order): array
    {
        $reservations = [];

        return DB::transaction(function () use ($order, &$reservations) {
            foreach ($order->articles as $article) {
                // Get default warehouse for the product's boutique
                $entrepot = $this->stockService->getDefaultEntrepot($article->produit->boutique_id);

                if (!$entrepot) {
                    throw new \Exception("No warehouse found for boutique: {$article->produit->boutique_id}");
                }

                $reservation = $this->createReservation(
                    $article->variante_id,
                    $entrepot->id,
                    $article->quantite,
                    [
                        'affilie_id' => $order->affilie_id,
                        'offre_id' => $order->offre_id,
                        'expire_at' => now()->addDays(30), // Order reservations expire in 30 days
                    ]
                );

                $reservations[] = $reservation;
            }

            Log::info('Order reservations created', [
                'order_id' => $order->id,
                'reservations_count' => count($reservations),
                'total_quantity' => array_sum(array_column($reservations, 'quantite'))
            ]);

            return $reservations;
        });
    }

    /**
     * Release reservations for an order
     */
    public function releaseOrderReservations(\App\Models\Commande $order): int
    {
        $released = 0;

        return DB::transaction(function () use ($order, &$released) {
            // Find active reservations for this order's affiliate and offer
            $reservations = ReservationStock::where('statut', 'active')
                ->where('affilie_id', $order->affilie_id)
                ->where('offre_id', $order->offre_id)
                ->whereIn('variante_id', $order->articles->pluck('variante_id'))
                ->get();

            foreach ($reservations as $reservation) {
                $this->releaseReservation($reservation->id, 'order_cancelled');
                $released++;
            }

            Log::info('Order reservations released', [
                'order_id' => $order->id,
                'released_count' => $released
            ]);

            return $released;
        });
    }
}
