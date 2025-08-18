<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateStockMovementRequest;
use App\Services\StockService;
use App\Models\Produit;
use App\Models\ProduitVariante;
use App\Models\Stock;
use App\Models\Entrepot;
use App\Models\Boutique;
use App\Models\Categorie;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class StockController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Get stock list with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:255',
            'categorie_id' => 'nullable|uuid|exists:categories,id',
            'boutique_id' => 'nullable|uuid|exists:boutiques,id',
            'actif' => 'nullable|boolean',
            'with_variants' => 'nullable|boolean',
            'min_qty' => 'nullable|integer|min:0',
            'max_qty' => 'nullable|integer|min:0',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|in:product,variant,qty,available,updated_at',
            'dir' => 'nullable|in:asc,desc',
        ]);

        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $sort = $request->get('sort', 'updated_at');
        $dir = $request->get('dir', 'desc');
        $withVariants = $request->boolean('with_variants', true);

        // Base query for products
        $query = Produit::with(['boutique:id,nom', 'categorie:id,nom'])
            ->where('actif', true);

        // Apply filters
        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->get('categorie_id'));
        }

        if ($request->filled('boutique_id')) {
            $query->where('boutique_id', $request->get('boutique_id'));
        }

        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform products to include stock information
        $items = [];
        foreach ($products->items() as $product) {
            if ($withVariants && $product->variantes()->exists()) {
                // Include variants
                $variants = $product->variantes()->where('actif', true)->get();
                foreach ($variants as $variant) {
                    $stockSnapshot = $this->stockService->snapshot($variant->id);
                    $stats = $this->stockService->getStats($variant->id, null, 7);

                    // Apply quantity filters
                    if ($request->filled('min_qty') && $stockSnapshot['available'] < $request->get('min_qty')) {
                        continue;
                    }
                    if ($request->filled('max_qty') && $stockSnapshot['available'] > $request->get('max_qty')) {
                        continue;
                    }

                    $items[] = [
                        'product' => [
                            'id' => $product->id,
                            'titre' => $product->titre,
                            'slug' => $product->slug,
                            'categorie' => $product->categorie ? [
                                'id' => $product->categorie->id,
                                'nom' => $product->categorie->nom,
                            ] : null,
                            'boutique' => [
                                'id' => $product->boutique->id,
                                'nom' => $product->boutique->nom,
                            ],
                        ],
                        'variant' => [
                            'id' => $variant->id,
                            'libelle' => $variant->nom . ': ' . $variant->valeur,
                            'attributes' => $variant->nom . ': ' . $variant->valeur,
                            'image_url' => $variant->image_url,
                        ],
                        'metrics' => array_merge($stockSnapshot, [
                            'incoming' => 0, // TODO: Implement if needed
                        ]),
                        'kpis' => $stats,
                    ];
                }
            } else {
                // Product-level stock (sum of all variants or default variant)
                $variants = $product->variantes()->where('actif', true)->get();
                $totalOnHand = 0;
                $totalReserved = 0;
                $totalAvailable = 0;
                $lastMovementAt = null;
                $lastMovementType = null;

                if ($variants->isNotEmpty()) {
                    foreach ($variants as $variant) {
                        $snapshot = $this->stockService->snapshot($variant->id);
                        $totalOnHand += $snapshot['on_hand'];
                        $totalReserved += $snapshot['reserved'];
                        $totalAvailable += $snapshot['available'];
                        
                        if ($snapshot['last_movement_at'] && 
                            (!$lastMovementAt || $snapshot['last_movement_at'] > $lastMovementAt)) {
                            $lastMovementAt = $snapshot['last_movement_at'];
                            $lastMovementType = $snapshot['last_movement_type'];
                        }
                    }
                }

                // Apply quantity filters
                if ($request->filled('min_qty') && $totalAvailable < $request->get('min_qty')) {
                    continue;
                }
                if ($request->filled('max_qty') && $totalAvailable > $request->get('max_qty')) {
                    continue;
                }

                $items[] = [
                    'product' => [
                        'id' => $product->id,
                        'titre' => $product->titre,
                        'slug' => $product->slug,
                        'categorie' => $product->categorie ? [
                            'id' => $product->categorie->id,
                            'nom' => $product->categorie->nom,
                        ] : null,
                        'boutique' => [
                            'id' => $product->boutique->id,
                            'nom' => $product->boutique->nom,
                        ],
                    ],
                    'variant' => null,
                    'metrics' => [
                        'on_hand' => $totalOnHand,
                        'reserved' => $totalReserved,
                        'available' => $totalAvailable,
                        'incoming' => 0,
                        'last_movement_at' => $lastMovementAt,
                        'last_movement_type' => $lastMovementType,
                    ],
                    'kpis' => [
                        'sum_in' => 0, // TODO: Calculate if needed
                        'sum_out' => 0,
                        'adjustments' => 0,
                        'total_movements' => 0,
                    ],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    /**
     * Get stock summary statistics
     */
    public function summary(Request $request): JsonResponse
    {
        // Get totals
        $totalProducts = Produit::where('actif', true)->count();
        $totalVariants = ProduitVariante::where('actif', true)->count();
        
        $stockTotals = Stock::selectRaw('
            SUM(qte_disponible) as total_on_hand,
            SUM(qte_reservee) as total_reserved,
            SUM(qte_disponible - qte_reservee) as total_available
        ')->first();

        // Get top lowest stock items
        $topLowest = Stock::with(['variante.produit'])
            ->selectRaw('*, (qte_disponible - qte_reservee) as available')
            ->havingRaw('available >= 0')
            ->orderBy('available', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($stock) {
                return [
                    'id' => $stock->variante->produit->id,
                    'label' => $stock->variante->produit->titre . ' - ' . $stock->variante->nom . ': ' . $stock->variante->valeur,
                    'qty' => $stock->available,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'totals' => [
                    'products_count' => $totalProducts,
                    'variants_count' => $totalVariants,
                    'total_on_hand' => $stockTotals->total_on_hand ?? 0,
                    'total_reserved' => $stockTotals->total_reserved ?? 0,
                    'total_available' => $stockTotals->total_available ?? 0,
                ],
                'top_lowest' => $topLowest,
                'top_movers_in' => [], // TODO: Implement if needed
                'top_movers_out' => [], // TODO: Implement if needed
            ],
        ]);
    }

    /**
     * Create a stock movement
     */
    public function createMovement(CreateStockMovementRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Get the product
            $product = Produit::findOrFail($data['produit_id']);

            // Determine variant ID
            $varianteId = $data['variante_id'];
            if (!$varianteId) {
                // If no variant specified, get the first active variant or create a default one
                $variant = $product->variantes()->where('actif', true)->first();
                if (!$variant) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.no_variants_found'),
                    ], 422);
                }
                $varianteId = $variant->id;
            }

            // Determine warehouse ID
            $entrepotId = $data['entrepot_id'];
            if (!$entrepotId) {
                $entrepot = $this->stockService->getDefaultEntrepot($product->boutique_id);
                if (!$entrepot) {
                    return response()->json([
                        'success' => false,
                        'message' => __('messages.no_warehouse_found'),
                    ], 422);
                }
                $entrepotId = $entrepot->id;
            }

            // Create the movement
            $result = $this->stockService->move(
                $varianteId,
                $entrepotId,
                $data['type'],
                $data['quantity'],
                $data['reason'],
                $data['note'] ?? null,
                $data['reference'] ?? null,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => __('messages.stock_movement_created'),
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get stock movement history for a product
     */
    public function history(Request $request, string $produitId): JsonResponse
    {
        $request->validate([
            'variante_id' => 'nullable|uuid|exists:produit_variantes,id',
            'entrepot_id' => 'nullable|uuid|exists:entrepots,id',
            'type' => 'nullable|in:in,out,adjust',
            'reason' => 'nullable|in:purchase,correction,return,damage,manual,delivery_return,cancel',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $product = Produit::findOrFail($produitId);
        $perPage = $request->get('per_page', 15);

        // If specific variant requested, use it; otherwise get all variants for the product
        if ($request->filled('variante_id')) {
            $varianteId = $request->get('variante_id');

            // Verify variant belongs to product
            $variant = ProduitVariante::where('id', $varianteId)
                ->where('produit_id', $produitId)
                ->firstOrFail();
        } else {
            // Get first variant for the product
            $variant = $product->variantes()->where('actif', true)->first();
            if (!$variant) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                    ],
                ]);
            }
            $varianteId = $variant->id;
        }

        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->get('date_from')) : null;
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->get('date_to')) : null;

        $history = $this->stockService->getHistory(
            $varianteId,
            $request->get('entrepot_id'),
            $request->get('type'),
            $request->get('reason'),
            $dateFrom,
            $dateTo,
            $perPage
        );

        // Transform the data
        $items = $history->items();
        $transformedItems = collect($items)->map(function ($movement) {
            return [
                'id' => $movement->id,
                'type' => $movement->type,
                'quantity' => $movement->quantite,
                'reference' => $movement->reference,
                'created_at' => $movement->created_at,
                'variant' => [
                    'id' => $movement->variante->id,
                    'libelle' => $movement->variante->nom . ': ' . $movement->variante->valeur,
                ],
                'product' => [
                    'id' => $movement->variante->produit->id,
                    'titre' => $movement->variante->produit->titre,
                ],
                'entrepot' => $movement->entrepot ? [
                    'id' => $movement->entrepot->id,
                    'nom' => $movement->entrepot->nom,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedItems,
            'pagination' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'from' => $history->firstItem(),
                'to' => $history->lastItem(),
            ],
        ]);
    }
}
