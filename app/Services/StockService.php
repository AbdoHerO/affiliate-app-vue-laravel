<?php

namespace App\Services;

use App\Models\ProduitVariante;
use App\Models\Stock;
use App\Models\MouvementStock;
use App\Models\ReservationStock;
use App\Models\Entrepot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StockService
{
    /**
     * Get stock snapshot for a product variant
     */
    public function snapshot(string $varianteId, ?string $entrepotId = null): array
    {
        $query = Stock::where('variante_id', $varianteId);
        
        if ($entrepotId) {
            $query->where('entrepot_id', $entrepotId);
        }
        
        $stocks = $query->with(['variante.produit', 'entrepot'])->get();
        
        if ($stocks->isEmpty()) {
            return [
                'on_hand' => 0,
                'reserved' => 0,
                'available' => 0,
                'last_movement_at' => null,
                'last_movement_type' => null,
            ];
        }
        
        $totalOnHand = $stocks->sum('qte_disponible');
        $totalReserved = $stocks->sum('qte_reservee');
        
        // Get last movement
        $lastMovement = MouvementStock::where('variante_id', $varianteId)
            ->when($entrepotId, fn($q) => $q->where('entrepot_id', $entrepotId))
            ->orderBy('created_at', 'desc')
            ->first();
        
        return [
            'on_hand' => $totalOnHand,
            'reserved' => $totalReserved,
            'available' => max(0, $totalOnHand - $totalReserved),
            'last_movement_at' => $lastMovement?->created_at,
            'last_movement_type' => $lastMovement?->type,
        ];
    }
    
    /**
     * Get available quantity for a product variant
     */
    public function available(string $varianteId, ?string $entrepotId = null): int
    {
        $snapshot = $this->snapshot($varianteId, $entrepotId);
        return $snapshot['available'];
    }
    
    /**
     * Create a stock movement and update stock levels
     */
    public function move(
        string $varianteId,
        string $entrepotId,
        string $type,
        int $quantity,
        string $reason,
        ?string $note = null,
        ?string $reference = null,
        ?string $performedBy = null
    ): array {
        return DB::transaction(function () use (
            $varianteId, $entrepotId, $type, $quantity, $reason, $note, $reference, $performedBy
        ) {
            // Validate movement type
            if (!in_array($type, ['in', 'out', 'adjust'])) {
                throw new \InvalidArgumentException("Invalid movement type: {$type}");
            }
            
            // Validate reason
            $validReasons = ['purchase', 'correction', 'return', 'damage', 'manual', 'delivery_return', 'delivery_shipment', 'cancel'];
            if (!in_array($reason, $validReasons)) {
                throw new \InvalidArgumentException("Invalid reason: {$reason}");
            }
            
            // Get current stock or create if doesn't exist
            $stock = Stock::firstOrCreate(
                ['variante_id' => $varianteId, 'entrepot_id' => $entrepotId],
                ['qte_disponible' => 0, 'qte_reservee' => 0]
            );
            
            $beforeSnapshot = [
                'on_hand' => $stock->qte_disponible,
                'reserved' => $stock->qte_reservee,
                'available' => max(0, $stock->qte_disponible - $stock->qte_reservee),
            ];
            
            // Calculate new quantities based on movement type
            $newOnHand = $stock->qte_disponible;
            
            switch ($type) {
                case 'in':
                    $newOnHand += $quantity;
                    break;
                case 'out':
                    // Check if we have enough available stock
                    $available = max(0, $stock->qte_disponible - $stock->qte_reservee);
                    if ($available < $quantity) {
                        throw new \Exception("Insufficient available stock. Available: {$available}, Requested: {$quantity}");
                    }
                    $newOnHand -= $quantity;
                    break;
                case 'adjust':
                    // For adjustments, quantity can be positive or negative
                    $newOnHand = $quantity;
                    break;
            }
            
            // Ensure stock doesn't go negative
            if ($newOnHand < 0) {
                throw new \Exception("Stock cannot be negative. Current: {$stock->qte_disponible}, Change: {$quantity}");
            }
            
            // Update stock
            $stock->update(['qte_disponible' => $newOnHand]);
            
            // Create movement record
            $movement = MouvementStock::create([
                'variante_id' => $varianteId,
                'entrepot_id' => $entrepotId,
                'type' => $type,
                'quantite' => $type === 'out' ? -$quantity : $quantity, // Store negative for out movements
                'reference' => $reference,
                'created_at' => now(),
            ]);
            
            // Log the movement
            Log::info('Stock movement created', [
                'movement_id' => $movement->id,
                'variante_id' => $varianteId,
                'entrepot_id' => $entrepotId,
                'type' => $type,
                'quantity' => $quantity,
                'reason' => $reason,
                'performed_by' => $performedBy,
                'before' => $beforeSnapshot,
                'after' => [
                    'on_hand' => $newOnHand,
                    'reserved' => $stock->qte_reservee,
                    'available' => max(0, $newOnHand - $stock->qte_reservee),
                ],
            ]);

            // Update product global stock after variant stock change
            $variant = \App\Models\ProduitVariante::find($varianteId);
            if ($variant && $variant->produit_id) {
                try {
                    $this->updateProductGlobalStock($variant->produit_id);
                } catch (\Exception $e) {
                    // Log error but don't fail the stock movement
                    Log::warning('Failed to update product global stock', [
                        'product_id' => $variant->produit_id,
                        'variant_id' => $varianteId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'movement' => $movement,
                'snapshot' => [
                    'on_hand' => $newOnHand,
                    'reserved' => $stock->qte_reservee,
                    'available' => max(0, $newOnHand - $stock->qte_reservee),
                    'last_movement_at' => $movement->created_at,
                    'last_movement_type' => $type,
                ],
            ];
        });
    }
    
    /**
     * Get stock movements history for a product variant
     */
    public function getHistory(
        string $varianteId,
        ?string $entrepotId = null,
        ?string $type = null,
        ?string $reason = null,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        int $perPage = 15
    ) {
        $query = MouvementStock::where('variante_id', $varianteId)
            ->with(['variante.produit', 'entrepot'])
            ->orderBy('created_at', 'desc');
        
        if ($entrepotId) {
            $query->where('entrepot_id', $entrepotId);
        }
        
        if ($type) {
            $query->where('type', $type);
        }
        
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }
        
        return $query->paginate($perPage);
    }
    
    /**
     * Get stock statistics for a date range
     */
    public function getStats(
        string $varianteId,
        ?string $entrepotId = null,
        int $days = 30
    ): array {
        $dateFrom = now()->subDays($days);
        
        $query = MouvementStock::where('variante_id', $varianteId)
            ->where('created_at', '>=', $dateFrom);
        
        if ($entrepotId) {
            $query->where('entrepot_id', $entrepotId);
        }
        
        $movements = $query->get();
        
        return [
            'sum_in' => $movements->where('type', 'in')->sum('quantite'),
            'sum_out' => abs($movements->where('type', 'out')->sum('quantite')),
            'adjustments' => $movements->where('type', 'adjust')->sum('quantite'),
            'total_movements' => $movements->count(),
        ];
    }
    
    /**
     * Get default warehouse for a boutique
     */
    public function getDefaultEntrepot(string $boutiqueId): ?Entrepot
    {
        return Entrepot::where('boutique_id', $boutiqueId)
            ->where('actif', true)
            ->first();
    }

    /**
     * Synchronize reserved quantities from reservations table
     */
    public function syncReservedQuantities(?string $varianteId = null, ?string $entrepotId = null): int
    {
        $query = Stock::query();

        if ($varianteId) {
            $query->where('variante_id', $varianteId);
        }

        if ($entrepotId) {
            $query->where('entrepot_id', $entrepotId);
        }

        $stocks = $query->get();
        $updated = 0;

        foreach ($stocks as $stock) {
            $totalReserved = ReservationStock::where('variante_id', $stock->variante_id)
                ->where('entrepot_id', $stock->entrepot_id)
                ->where('statut', 'active')
                ->sum('quantite');

            if ($stock->qte_reservee !== $totalReserved) {
                $stock->update(['qte_reservee' => $totalReserved]);
                $updated++;
            }
        }

        Log::info('Reserved quantities synchronized', [
            'updated_count' => $updated,
            'variante_id' => $varianteId,
            'entrepot_id' => $entrepotId
        ]);

        return $updated;
    }

    /**
     * Calculate and update the global stock for a product
     */
    public function updateProductGlobalStock(string $productId): array
    {
        $product = \App\Models\Produit::findOrFail($productId);

        // Calculate total stock from all variants
        $totalStock = 0;
        $totalReserved = 0;
        $variantDetails = [];

        foreach ($product->variantes as $variant) {
            $variantStock = Stock::where('variante_id', $variant->id)->sum('qte_disponible');
            $variantReserved = Stock::where('variante_id', $variant->id)->sum('qte_reservee');

            $totalStock += $variantStock;
            $totalReserved += $variantReserved;

            $variantDetails[] = [
                'variant_id' => $variant->id,
                'variant_name' => $variant->nom . ': ' . $variant->valeur,
                'stock' => $variantStock,
                'reserved' => $variantReserved,
                'available' => max(0, $variantStock - $variantReserved),
            ];
        }

        // Update product's global stock
        $product->update(['stock_total' => $totalStock]);

        Log::info('Product global stock updated', [
            'product_id' => $productId,
            'total_stock' => $totalStock,
            'total_reserved' => $totalReserved,
            'variant_count' => count($variantDetails)
        ]);

        return [
            'product_id' => $productId,
            'total_stock' => $totalStock,
            'total_reserved' => $totalReserved,
            'total_available' => max(0, $totalStock - $totalReserved),
            'variants' => $variantDetails,
            'updated_at' => now()->toISOString(),
        ];
    }

    /**
     * Synchronize global stock for all products or specific boutique
     */
    public function syncAllProductsGlobalStock(?string $boutiqueId = null): array
    {
        $query = \App\Models\Produit::with('variantes');

        if ($boutiqueId) {
            $query->where('boutique_id', $boutiqueId);
        }

        $products = $query->get();
        $results = [];
        $totalProcessed = 0;
        $totalUpdated = 0;

        foreach ($products as $product) {
            $result = $this->updateProductGlobalStock($product->id);
            $results[] = $result;
            $totalProcessed++;

            if ($result['total_stock'] !== $product->getOriginal('stock_total')) {
                $totalUpdated++;
            }
        }

        Log::info('Global stock synchronization completed', [
            'boutique_id' => $boutiqueId,
            'total_processed' => $totalProcessed,
            'total_updated' => $totalUpdated
        ]);

        return [
            'success' => true,
            'total_processed' => $totalProcessed,
            'total_updated' => $totalUpdated,
            'boutique_id' => $boutiqueId,
            'results' => $results,
        ];
    }
}
