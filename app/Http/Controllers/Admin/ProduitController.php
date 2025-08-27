<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProduitRequest;
use App\Http\Requests\Admin\UpdateProduitRequest;
use App\Http\Resources\ProduitResource;
use App\Models\Produit;
use App\Services\StockAllocationService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProduitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Produit::with(['boutique:id,nom', 'categorie:id,nom', 'images']);

        // Handle soft delete filtering
        $includeDeleted = $request->get('include_deleted', 'active'); // active, trashed, all
        switch ($includeDeleted) {
            case 'trashed':
                $query->onlyTrashed();
                break;
            case 'all':
                $query->withTrashed();
                break;
            case 'active':
            default:
                // Default behavior - only active (non-deleted) records
                break;
        }

        // Search by title, slug, or SKU
        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Filter by boutique
        if ($request->filled('boutique_id')) {
            $query->where('boutique_id', $request->get('boutique_id'));
        }

        // Filter by category
        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->get('categorie_id'));
        }

        // Filter by status - handle '1', '0', and empty string
        if ($request->has('actif') && $request->get('actif') !== '') {
            $actif = $request->get('actif') === '1';
            $query->where('actif', $actif);
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('dir', 'desc'); // Changed from 'direction' to 'dir'

        $allowedSorts = ['titre', 'prix_vente', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->get('perPage', 15);
        $perPage = in_array((int)$perPage, [10, 15, 25, 50, 100]) ? (int)$perPage : 15;

        $page = $request->get('page', 1);
        $produits = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => ProduitResource::collection($produits->items()),
            'meta' => [
                'current_page' => $produits->currentPage(),
                'last_page' => $produits->lastPage(),
                'per_page' => $produits->perPage(),
                'total' => $produits->total(),
                'from' => $produits->firstItem(),
                'to' => $produits->lastItem(),
            ],
            'links' => [
                'first' => $produits->url(1),
                'last' => $produits->url($produits->lastPage()),
                'prev' => $produits->previousPageUrl(),
                'next' => $produits->nextPageUrl(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProduitRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Ensure quantite_min has a default value
            if (!isset($validated['quantite_min']) || $validated['quantite_min'] === null) {
                $validated['quantite_min'] = 1;
            }

            // Generate slug if not provided
            if (empty($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['titre']);
            }

            // Handle rating fields
            if (isset($validated['rating_value']) && $validated['rating_value'] !== null) {
                // Clamp rating value to [0, 5] and round to 1 decimal place
                $validated['rating_value'] = round(max(0, min(5, $validated['rating_value'])), 1);
                $validated['rating_updated_by'] = $request->user()->id;
                $validated['rating_updated_at'] = now();
            }

            $produit = Produit::create($validated);
            $produit->load(['boutique', 'categorie', 'images', 'ratingUpdater:id,nom_complet']);

            return response()->json([
                'success' => true,
                'message' => __('messages.produits.created_successfully'),
                'data' => new ProduitResource($produit),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produits.creation_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Produit $produit): ProduitResource
    {
        $produit->load([
            'boutique',
            'categorie',
            'ratingUpdater:id,nom_complet',
            'images' => function ($query) {
                $query->orderBy('ordre', 'asc');
            },
            'videos' => function ($query) {
                $query->orderBy('ordre', 'asc');
            },
            'variantes' => function ($query) {
                $query->orderBy('nom', 'asc')->orderBy('valeur', 'asc');
            },
            'propositions' => function ($query) {
                $query->with('auteur:id,nom_complet,email')->orderBy('created_at', 'desc');
            },
            'ruptures' => function ($query) {
                $query->orderBy('started_at', 'desc');
            }
        ]);

        return new ProduitResource($produit);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProduitRequest $request, Produit $produit): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Ensure quantite_min has a default value
            if (!isset($validated['quantite_min']) || $validated['quantite_min'] === null) {
                $validated['quantite_min'] = 1;
            }

            // Generate slug if not provided or if title changed
            if (empty($validated['slug']) || ($validated['titre'] !== $produit->titre)) {
                $validated['slug'] = Str::slug($validated['titre']);
            }

            // Handle rating fields
            if (isset($validated['rating_value']) && $validated['rating_value'] !== null) {
                // Clamp rating value to [0, 5] and round to 1 decimal place
                $validated['rating_value'] = round(max(0, min(5, $validated['rating_value'])), 1);
                $validated['rating_updated_by'] = $request->user()->id;
                $validated['rating_updated_at'] = now();
            }

            $produit->update($validated);
            $produit->load(['boutique', 'categorie', 'images', 'ratingUpdater:id,nom_complet']);

            return response()->json([
                'success' => true,
                'message' => __('messages.produits.updated_successfully'),
                'data' => new ProduitResource($produit),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produits.update_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete the specified resource.
     */
    public function destroy(Produit $produit): JsonResponse
    {
        try {
            $produit->delete(); // This will now be a soft delete

            return response()->json([
                'success' => true,
                'message' => __('messages.produits.deleted_successfully'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produits.deletion_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft deleted product.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $produit = Produit::withTrashed()->findOrFail($id);

            if (!$produit->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.produits.not_deleted'),
                ], 400);
            }

            $produit->restore();
            $produit->load(['boutique', 'categorie', 'images']);

            return response()->json([
                'success' => true,
                'message' => __('messages.produits.restored_successfully'),
                'data' => new ProduitResource($produit),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produits.restore_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(string $id): JsonResponse
    {
        try {
            $produit = Produit::withTrashed()->findOrFail($id);

            // Check for related records that would prevent permanent deletion
            $constraints = $this->checkDeleteConstraints($produit);

            if (!empty($constraints)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.produits.permanent_delete_failed_constraints'),
                    'constraints' => $constraints,
                ], 409);
            }

            $produit->forceDelete();

            return response()->json([
                'success' => true,
                'message' => __('messages.produits.permanently_deleted'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produits.permanent_deletion_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check for constraints that would prevent deletion.
     */
    private function checkDeleteConstraints(Produit $produit): array
    {
        $constraints = [];

        // Check for related offers (if the relationship exists)
        if (method_exists($produit, 'offres') && $produit->offres()->exists()) {
            $constraints[] = __('messages.produits.has_related_offers');
        }

        // Check for related stock (if the relationship exists)
        if (method_exists($produit, 'stocks') && $produit->stocks()->exists()) {
            $constraints[] = __('messages.produits.has_related_stock');
        }

        // Check for related orders (if the relationship exists)
        if (method_exists($produit, 'commandeArticles') && $produit->commandeArticles()->exists()) {
            $constraints[] = __('messages.produits.has_related_orders');
        }

        return $constraints;
    }

    /**
     * Generate a shareable public link for the product
     */
    public function share(Produit $produit): JsonResponse
    {
        try {
            $publicUrl = url("/p/{$produit->slug}");

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $publicUrl,
                    'slug' => $produit->slug,
                    'titre' => $produit->titre
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating share link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Allocate stock to product variants
     */
    public function allocateStock(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'stock_total' => 'required|integer|min:0',
                'allocations' => 'required|array',
                'allocations.*.variante_id' => 'required|string|exists:produit_variantes,id',
                'allocations.*.qty' => 'required|integer|min:0',
                'warehouse_id' => 'nullable|string|exists:entrepots,id',
            ]);

            $stockService = new StockAllocationService();

            $result = $stockService->allocate(
                $id,
                $request->input('stock_total'),
                $request->input('allocations'),
                $request->input('warehouse_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock allocated successfully',
                'data' => $result
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error allocating stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock matrix for a product
     */
    public function getStockMatrix(Request $request, string $id): JsonResponse
    {
        try {
            $warehouseId = $request->query('warehouse_id');

            $stockService = new StockAllocationService();
            $matrix = $stockService->getProductVariantMatrix($id, $warehouseId);

            return response()->json([
                'success' => true,
                'data' => $matrix
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting stock matrix: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Size × Color combinations for a product
     */
    public function generateCombinations(string $id): JsonResponse
    {
        try {
            $stockService = new StockAllocationService();
            $result = $stockService->generateCombinations($id);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'combinations' => $result['combinations'],
                    'created' => $result['created']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating combinations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouses for the current user's boutiques
     */
    public function getWarehouses(Request $request): JsonResponse
    {
        try {
            $warehouseService = new WarehouseService();
            $boutiqueId = $request->query('boutique_id');

            // Build warehouse query
            $query = \App\Models\Entrepot::where('actif', true)
                ->with('boutique:id,nom');

            // Filter by boutique if provided
            if ($boutiqueId) {
                $query->where('boutique_id', $boutiqueId);
            }

            $warehouses = $query->orderBy('nom')->get();

            // If no warehouses exist, create default ones
            if ($warehouses->isEmpty()) {
                if ($boutiqueId) {
                    // Create warehouse for specific boutique
                    $warehouseService->getOrCreateDefaultWarehouse($boutiqueId);
                } else {
                    // Create warehouses for all boutiques
                    $boutiques = \App\Models\Boutique::all();
                    foreach ($boutiques as $boutique) {
                        $warehouseService->getOrCreateDefaultWarehouse($boutique->id);
                    }
                }

                // Reload warehouses after creation
                $query = \App\Models\Entrepot::where('actif', true)
                    ->with('boutique:id,nom');

                if ($boutiqueId) {
                    $query->where('boutique_id', $boutiqueId);
                }

                $warehouses = $query->orderBy('nom')->get();
            }

            $warehouseData = $warehouses->map(function ($warehouse) {
                return [
                    'id' => $warehouse->id,
                    'name' => $warehouse->nom,
                    'boutique' => $warehouse->boutique->nom ?? 'Unknown',
                    'is_default' => true, // For now, mark all as default
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $warehouseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting warehouses: ' . $e->getMessage()
            ], 500);
        }
    }
}
