<?php

namespace App\Services;

use App\Models\Produit;
use App\Models\ProduitVariante;
use App\Models\MouvementStock;
use App\Models\Stock;
use App\Models\Entrepot;
use App\Services\WarehouseService;
use App\Services\VariantCombinationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAllocationService
{
    protected WarehouseService $warehouseService;
    protected VariantCombinationService $combinationService;

    public function __construct()
    {
        $this->warehouseService = new WarehouseService();
        $this->combinationService = new VariantCombinationService();
    }

    /**
     * Allocate stock to product variant combinations
     *
     * @param string $productId
     * @param int $stockTotal
     * @param array $allocations [['variante_id' => string, 'qty' => int], ...]
     * @param string|null $warehouseId
     * @return array Updated stock snapshot
     * @throws \Exception
     */
    public function allocate(string $productId, int $stockTotal, array $allocations, ?string $warehouseId = null): array
    {
        return DB::transaction(function () use ($productId, $stockTotal, $allocations, $warehouseId) {
            // Validate product exists
            $product = Produit::findOrFail($productId);

            // Get or create warehouse
            if ($warehouseId) {
                $warehouse = $this->warehouseService->getWarehouse($warehouseId, $product->boutique_id);
            } else {
                $warehouse = $this->warehouseService->getDefaultWarehouseForProduct($product);
            }

            // Deduplicate allocations by variant_id (sum quantities for duplicates)
            $deduplicatedAllocations = [];
            foreach ($allocations as $allocation) {
                $variantId = $allocation['variante_id'];
                if (isset($deduplicatedAllocations[$variantId])) {
                    $deduplicatedAllocations[$variantId]['qty'] += $allocation['qty'];
                } else {
                    $deduplicatedAllocations[$variantId] = $allocation;
                }
            }
            $allocations = array_values($deduplicatedAllocations);

            // Validate all variant IDs belong to this product
            $variantIds = collect($allocations)->pluck('variante_id')->unique()->toArray();
            $variants = ProduitVariante::where('produit_id', $productId)
                ->whereIn('id', $variantIds)
                ->with(['attribut', 'valeur'])
                ->get();

            if ($variants->count() !== count($variantIds)) {
                throw new \Exception('Some variant IDs are invalid or do not belong to this product');
            }

            // Validate stock allocation totals
            $totalAllocated = array_sum(array_column($allocations, 'qty'));
            if ($totalAllocated > $stockTotal) {
                throw new \Exception("Stock allocation error: Total allocated quantity ({$totalAllocated}) exceeds available stock ({$stockTotal})");
            }

            // Get current stock snapshot for these variants
            $currentStocks = $this->getCurrentStockSnapshot($variantIds, $warehouse->id);

            // Calculate deltas and create movements
            $movements = [];
            $updatedSnapshot = [];

            foreach ($allocations as $allocation) {
                $variantId = $allocation['variante_id'];
                $newQty = $allocation['qty'];
                $currentQty = $currentStocks[$variantId]['on_hand'] ?? 0;
                $reserved = $currentStocks[$variantId]['reserved'] ?? 0;

                $delta = $newQty - $currentQty;

                if ($delta !== 0) {
                    // Create stock movement using the correct structure
                    $movement = MouvementStock::create([
                        'variante_id' => $variantId,
                        'entrepot_id' => $warehouse->id,
                        'type' => 'ajustement', // Use 'ajustement' as per the schema
                        'quantite' => $newQty, // Store the final quantity for adjustments
                        'reference' => 'Stock allocation from product form',
                    ]);

                    $movements[] = $movement;

                    // Update or create stock record
                    Stock::updateOrCreate(
                        [
                            'variante_id' => $variantId,
                            'entrepot_id' => $warehouse->id,
                        ],
                        [
                            'qte_disponible' => $newQty,
                        ]
                    );
                }

                // Build updated snapshot
                $updatedSnapshot[$variantId] = [
                    'variante_id' => $variantId,
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
                'warehouse_id' => $warehouse->id,
                'stock_total' => $stockTotal,
                'allocations_count' => count($allocations),
                'movements_created' => count($movements),
            ]);

            return [
                'allocations' => $updatedSnapshot,
                'warehouse' => [
                    'id' => $warehouse->id,
                    'name' => $warehouse->nom,
                ],
                'summary' => [
                    'rows' => count($allocations),
                    'sum_allocations' => array_sum(array_column($allocations, 'qty')),
                    'stock_total' => $stockTotal,
                ],
            ];
        });
    }
    
    /**
     * Get current stock snapshot for variants in a specific warehouse
     *
     * @param array $variantIds
     * @param string $warehouseId
     * @return array
     */
    private function getCurrentStockSnapshot(array $variantIds, string $warehouseId): array
    {
        $snapshot = [];

        foreach ($variantIds as $variantId) {
            // Use the existing Stock table for current quantities
            $stock = Stock::where('variante_id', $variantId)
                ->where('entrepot_id', $warehouseId)
                ->first();

            if ($stock) {
                $snapshot[$variantId] = [
                    'on_hand' => $stock->qte_disponible,
                    'reserved' => $stock->qte_reservee,
                    'available' => max(0, $stock->qte_disponible - $stock->qte_reservee),
                ];
            } else {
                // No stock record exists yet
                $snapshot[$variantId] = [
                    'on_hand' => 0,
                    'reserved' => 0,
                    'available' => 0,
                ];
            }
        }

        return $snapshot;
    }
    
    /**
     * Get variant combinations matrix for a product
     *
     * @param string $productId
     * @param string|null $warehouseId
     * @return array
     */
    public function getProductVariantMatrix(string $productId, ?string $warehouseId = null): array
    {
        $product = Produit::findOrFail($productId);

        // Get or determine warehouse
        if ($warehouseId) {
            $warehouse = $this->warehouseService->getWarehouse($warehouseId, $product->boutique_id);
        } else {
            $warehouse = $this->warehouseService->getDefaultWarehouseForProduct($product);
        }

        // Use the combination service to get the matrix
        $matrixData = $this->combinationService->getSizeColorMatrix($productId, $warehouse->id);

        return [
            'matrix' => $matrixData['matrix'],
            'warehouse' => [
                'id' => $warehouse->id,
                'name' => $warehouse->nom,
            ],
            'summary' => [
                'matrix_rows' => count($matrixData['matrix']),
                'has_combinations' => $matrixData['has_combinations'],
                'has_virtual' => $matrixData['has_virtual'],
                'size_count' => $matrixData['size_count'],
                'color_count' => $matrixData['color_count'],
                'combination_count' => $matrixData['combination_count'],
            ],
        ];
    }

    /**
     * Generate Size × Color combinations for a product
     *
     * @param string $productId
     * @return array
     */
    public function generateCombinations(string $productId): array
    {
        return $this->combinationService->generateSizeColorCombinations($productId);
    }
}
