<?php

namespace App\Services;

use App\Models\Entrepot;
use App\Models\Boutique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseService
{
    /**
     * Get or create default warehouse for a boutique
     *
     * @param string $boutiqueId
     * @return Entrepot
     */
    public function getOrCreateDefaultWarehouse(string $boutiqueId): Entrepot
    {
        // First, try to find an existing active warehouse for this boutique
        $warehouse = Entrepot::where('boutique_id', $boutiqueId)
            ->where('actif', true)
            ->first();

        if ($warehouse) {
            return $warehouse;
        }

        // If no warehouse exists, create a default one
        return $this->createDefaultWarehouse($boutiqueId);
    }

    /**
     * Create a default warehouse for a boutique
     *
     * @param string $boutiqueId
     * @return Entrepot
     */
    public function createDefaultWarehouse(string $boutiqueId): Entrepot
    {
        return DB::transaction(function () use ($boutiqueId) {
            // Validate boutique exists
            $boutique = Boutique::findOrFail($boutiqueId);

            // Create default warehouse
            $warehouse = Entrepot::create([
                'boutique_id' => $boutiqueId,
                'nom' => 'Entrepôt principal',
                'adresse' => $boutique->adresse ?? 'Adresse non spécifiée',
                'actif' => true,
            ]);

            Log::info('Default warehouse created', [
                'warehouse_id' => $warehouse->id,
                'boutique_id' => $boutiqueId,
                'boutique_name' => $boutique->nom,
            ]);

            return $warehouse;
        });
    }

    /**
     * Get all active warehouses for a boutique
     *
     * @param string $boutiqueId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWarehousesForBoutique(string $boutiqueId)
    {
        return Entrepot::where('boutique_id', $boutiqueId)
            ->where('actif', true)
            ->orderBy('nom')
            ->get();
    }

    /**
     * Get warehouse by ID with validation
     *
     * @param string $warehouseId
     * @param string|null $boutiqueId
     * @return Entrepot
     * @throws \Exception
     */
    public function getWarehouse(string $warehouseId, ?string $boutiqueId = null): Entrepot
    {
        $query = Entrepot::where('id', $warehouseId)
            ->where('actif', true);

        if ($boutiqueId) {
            $query->where('boutique_id', $boutiqueId);
        }

        $warehouse = $query->first();

        if (!$warehouse) {
            throw new \Exception("Warehouse not found or inactive: {$warehouseId}");
        }

        return $warehouse;
    }

    /**
     * Get default warehouse for a product (via its boutique)
     *
     * @param \App\Models\Produit $product
     * @return Entrepot
     */
    public function getDefaultWarehouseForProduct($product): Entrepot
    {
        return $this->getOrCreateDefaultWarehouse($product->boutique_id);
    }
}
