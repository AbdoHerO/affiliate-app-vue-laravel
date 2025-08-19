<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CatalogueController extends Controller
{
    /**
     * Get products catalogue for affiliates with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Produit::query()
                ->where('actif', true)
                ->with([
                    'boutique:id,nom',
                    'categorie:id,nom',
                    'images' => function ($query) {
                        $query->orderBy('ordre', 'asc');
                    },
                    'variantes' => function ($query) {
                        $query->where('actif', true)->orderBy('nom', 'asc')
                              ->with(['stocks' => function ($stockQuery) {
                                  $stockQuery->selectRaw('variante_id, SUM(qte_disponible) as total_stock')
                                             ->groupBy('variante_id');
                              }]);
                    }
                ]);

            // Apply filters
            if ($request->filled('q')) {
                $searchTerm = $request->input('q');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('titre', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%");
                });
            }

            if ($request->filled('category_id')) {
                $query->where('categorie_id', $request->input('category_id'));
            }

            if ($request->filled('min_profit')) {
                $minProfit = (float) $request->input('min_profit');
                $query->where('prix_affilie', '>=', $minProfit);
            }

            // Filter by variant attributes (size, color)
            if ($request->filled('size')) {
                $size = $request->input('size');
                $query->whereHas('variantes', function ($q) use ($size) {
                    $q->where('actif', true)
                      ->where('nom', 'taille')
                      ->where('valeur', $size);
                });
            }

            if ($request->filled('color')) {
                $color = $request->input('color');
                $query->whereHas('variantes', function ($q) use ($color) {
                    $q->where('actif', true)
                      ->where('nom', 'couleur')
                      ->where('valeur', $color);
                });
            }

            // We'll calculate stock total manually in the transformation

            // Sorting
            $sortField = $request->input('sort', 'created_at');
            $sortDirection = $request->input('dir', 'desc');
            
            $allowedSortFields = ['created_at', 'titre', 'prix_vente', 'prix_affilie', 'stock_total'];
            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            // Pagination
            $perPage = min((int) $request->input('per_page', 12), 50); // Max 50 items per page
            $products = $query->paginate($perPage);

            // Transform data for frontend
            $transformedData = $products->getCollection()->map(function ($product) {
                return [
                    'id' => $product->id,
                    'titre' => $product->titre,
                    'description' => $product->description,
                    'slug' => $product->slug,
                    'prix_achat' => (float) $product->prix_achat,
                    'prix_vente' => (float) $product->prix_vente,
                    'prix_affilie' => (float) $product->prix_affilie,
                    'stock_total' => (int) ($product->stock_total ?? 0),
                    'rating_value' => $product->rating_value ? (float) $product->rating_value : null,
                    'categorie' => $product->categorie ? [
                        'id' => $product->categorie->id,
                        'nom' => $product->categorie->nom,
                    ] : null,
                    'images' => $product->images->map(function ($image) {
                        return [
                            'url' => $image->url,
                            'ordre' => $image->ordre,
                        ];
                    }),
                    'variantes' => $product->variantes->map(function ($variant) {
                        $totalStock = $variant->stocks->sum('qte_disponible') ?? 0;
                        return [
                            'id' => $variant->id,
                            'attribut_principal' => $variant->nom, // Use 'nom' instead of 'attribut_principal'
                            'valeur' => $variant->valeur,
                            'color' => $variant->color ?? null,
                            'image_url' => $variant->image_url,
                            'stock' => (int) $totalStock,
                        ];
                    }),
                ];
            });

            return response()->json([
                'data' => $transformedData,
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du chargement du catalogue',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Get single product details for affiliate
     */
    public function show(string $id): JsonResponse
    {
        try {
            $product = Produit::where('id', $id)
                ->where('actif', true)
                ->with([
                    'boutique:id,nom',
                    'categorie:id,nom',
                    'images' => function ($query) {
                        $query->orderBy('ordre', 'asc');
                    },
                    'videos' => function ($query) {
                        $query->orderBy('ordre', 'asc');
                    },
                    'variantes' => function ($query) {
                        $query->where('actif', true)->orderBy('nom', 'asc')
                              ->with(['stocks']);
                    },
                    'propositions' => function ($query) {
                        $query->where('statut', 'approuve')->orderBy('created_at', 'desc');
                    }
                ])
                ->first();

            if (!$product) {
                return response()->json([
                    'message' => 'Produit non trouvé',
                    'success' => false
                ], 404);
            }

            // Calculate total stock from all variants
            $stockTotal = $product->variantes->sum(function ($variant) {
                return $variant->stocks->sum('qte_disponible');
            });

            $transformedProduct = [
                'id' => $product->id,
                'titre' => $product->titre,
                'description' => $product->description,
                'slug' => $product->slug,
                'prix_achat' => (float) $product->prix_achat,
                'prix_vente' => (float) $product->prix_vente,
                'prix_affilie' => (float) $product->prix_affilie,
                'stock_total' => (int) $stockTotal,
                'rating_value' => $product->rating_value ? (float) $product->rating_value : null,
                'categorie' => $product->categorie ? [
                    'id' => $product->categorie->id,
                    'nom' => $product->categorie->nom,
                ] : null,
                'boutique' => $product->boutique ? [
                    'id' => $product->boutique->id,
                    'nom' => $product->boutique->nom,
                ] : null,
                'images' => $product->images->map(function ($image) {
                    return [
                        'url' => $image->url,
                        'ordre' => $image->ordre,
                    ];
                }),
                'videos' => $product->videos->map(function ($video) {
                    return [
                        'url' => $video->url,
                        'type' => $video->type,
                        'ordre' => $video->ordre,
                    ];
                }),
                'variantes' => $product->variantes->map(function ($variant) {
                    $totalStock = $variant->stocks->sum('qte_disponible') ?? 0;
                    return [
                        'id' => $variant->id,
                        'attribut_principal' => $variant->nom,
                        'valeur' => $variant->valeur,
                        'color' => $variant->color ?? null,
                        'image_url' => $variant->image_url,
                        'stock' => (int) $totalStock,
                    ];
                }),
                'propositions' => $product->propositions->map(function ($proposition) {
                    return [
                        'id' => $proposition->id,
                        'titre' => $proposition->titre,
                        'description' => $proposition->description,
                        'prix' => (float) $proposition->prix,
                    ];
                }),
            ];

            return response()->json($transformedProduct);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du chargement du produit',
                'error' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
}
