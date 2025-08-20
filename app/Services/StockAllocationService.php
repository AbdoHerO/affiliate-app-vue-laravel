<?php

namespace App\Services;

use App\Models\Produit;
use App\Models\ProduitVariante;
use App\Models\MouvementStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAllocationService
{
    /**
     * Allocate stock to product variants
     *
     * @param string $productId
     * @param int $stockTotal
     * @param array $variantStocks [['variante_id' => string, 'qty' => int], ...]
     * @return array Updated stock snapshot
     * @throws \Exception
     */
    public function allocate(string $productId, int $stockTotal, array $variantStocks): array
    {
        return DB::transaction(function () use ($productId, $stockTotal, $variantStocks) {
            // Validate product exists
            $product = Produit::findOrFail($productId);
            
            // Validate all variant IDs belong to this product and are size variants
            $variantIds = collect($variantStocks)->pluck('variante_id')->toArray();
            $variants = ProduitVariante::where('produit_id', $productId)
                ->whereIn('id', $variantIds)
                ->get();
            
            if ($variants->count() !== count($variantIds)) {
                throw new \Exception('Some variant IDs are invalid or do not belong to this product');
            }
            
            // Validate these are size variants
            $sizeVariants = $variants->filter(function ($variant) {
                return in_array(strtolower($variant->nom), ['taille', 'size']);
            });
            
            if ($sizeVariants->count() !== $variants->count()) {
                throw new \Exception('All variants must be size variants for stock allocation');
            }
            
            // Get current stock snapshot for these variants
            $currentStocks = $this->getCurrentStockSnapshot($variantIds);
            
            // Calculate deltas and create movements
            $movements = [];
            $updatedSnapshot = [];
            
            foreach ($variantStocks as $variantStock) {
                $variantId = $variantStock['variante_id'];
                $newQty = $variantStock['qty'];
                $currentQty = $currentStocks[$variantId]['on_hand'] ?? 0;
                $reserved = $currentStocks[$variantId]['reserved'] ?? 0;
                
                $delta = $newQty - $currentQty;
                
                if ($delta !== 0) {
                    // Create stock movement
                    $movement = MouvementStock::create([
                        'variante_id' => $variantId,
                        'type' => 'adjust',
                        'quantite' => abs($delta),
                        'direction' => $delta > 0 ? 'in' : 'out',
                        'motif' => 'manual',
                        'notes' => 'Stock allocation from product form',
                        'auteur_id' => auth()->id(),
                    ]);
                    
                    $movements[] = $movement;
                }
                
                // Build updated snapshot
                $updatedSnapshot[$variantId] = [
                    'on_hand' => $newQty,
                    'reserved' => $reserved,
                    'available' => max(0, $newQty - $reserved),
                ];
            }
            
            // Update product stock_total if provided
            if ($stockTotal !== null) {
                $product->update(['stock_total' => $stockTotal]);
            }
            
            Log::info('Stock allocation completed', [
                'product_id' => $productId,
                'stock_total' => $stockTotal,
                'movements_created' => count($movements),
                'updated_snapshot' => $updatedSnapshot,
            ]);
            
            return $updatedSnapshot;
        });
    }
    
    /**
     * Get current stock snapshot for variants
     *
     * @param array $variantIds
     * @return array
     */
    private function getCurrentStockSnapshot(array $variantIds): array
    {
        $snapshot = [];
        
        foreach ($variantIds as $variantId) {
            // Calculate current stock from movements
            $inMovements = MouvementStock::where('variante_id', $variantId)
                ->where('direction', 'in')
                ->sum('quantite');
                
            $outMovements = MouvementStock::where('variante_id', $variantId)
                ->where('direction', 'out')
                ->sum('quantite');
                
            $onHand = $inMovements - $outMovements;
            
            // Get reserved quantity (this would come from reservations table if implemented)
            $reserved = 0; // TODO: Implement reservations calculation
            
            $snapshot[$variantId] = [
                'on_hand' => max(0, $onHand),
                'reserved' => $reserved,
                'available' => max(0, $onHand - $reserved),
            ];
        }
        
        return $snapshot;
    }
    
    /**
     * Get stock summary for a product's size variants
     *
     * @param string $productId
     * @return array
     */
    public function getProductStockSummary(string $productId): array
    {
        $sizeVariants = ProduitVariante::where('produit_id', $productId)
            ->whereIn('nom', ['taille', 'size', 'Taille', 'Size'])
            ->get();
            
        $variantIds = $sizeVariants->pluck('id')->toArray();
        $stockSnapshot = $this->getCurrentStockSnapshot($variantIds);
        
        $summary = [];
        foreach ($sizeVariants as $variant) {
            $stock = $stockSnapshot[$variant->id] ?? ['on_hand' => 0, 'reserved' => 0, 'available' => 0];
            $summary[] = [
                'variante_id' => $variant->id,
                'size' => $variant->valeur,
                'qty' => $stock['on_hand'],
                'reserved' => $stock['reserved'],
                'available' => $stock['available'],
            ];
        }
        
        return $summary;
    }
}
