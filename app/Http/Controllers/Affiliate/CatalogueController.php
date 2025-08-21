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
                    'videos' => function ($query) {
                        $query->orderBy('ordre', 'asc');
                    },
                    'variantes' => function ($query) {
                        $query->where('actif', true)->orderBy('nom', 'asc')
                              ->with(['stocks', 'attribut', 'valeur']);
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
                // Get the product's boutique warehouse
                $warehouse = \App\Models\Entrepot::where('boutique_id', $product->boutique_id)
                    ->where('actif', true)
                    ->first();

                // Calculate total stock from variants in the correct warehouse only
                $totalStock = 0;
                if ($warehouse) {
                    $totalStock = $product->variantes->sum(function ($variant) use ($warehouse) {
                        return $variant->stocks->where('entrepot_id', $warehouse->id)->sum('qte_disponible');
                    });
                }

                return [
                    'id' => $product->id,
                    'titre' => $product->titre,
                    'description' => $product->description,
                    'copywriting' => $product->copywriting,
                    'slug' => $product->slug,
                    'prix_achat' => (float) $product->prix_achat,
                    'prix_vente' => (float) $product->prix_vente,
                    'prix_affilie' => (float) $product->prix_affilie,
                    'stock_total' => (int) $totalStock,
                    'rating_value' => $product->rating_value ? (float) $product->rating_value : null,
                    'categorie' => $product->categorie ? [
                        'id' => $product->categorie->id,
                        'nom' => $product->categorie->nom,
                    ] : null,
                    'images' => $product->images->map(function ($image) {
                        return [
                            'url' => $this->getFullImageUrl($image->url),
                            'ordre' => $image->ordre,
                        ];
                    }),
                    'videos' => $product->videos->map(function ($video) {
                        return [
                            'url' => $video->url,
                            'titre' => $video->titre,
                            'ordre' => $video->ordre,
                        ];
                    }),
                    'variantes' => $product->variantes->map(function ($variant) use ($warehouse) {
                        // Calculate variant stock from the correct warehouse only
                        $variantStock = 0;
                        if ($warehouse) {
                            $variantStock = $variant->stocks->where('entrepot_id', $warehouse->id)->sum('qte_disponible');
                        }

                        return [
                            'id' => $variant->id,
                            'attribut_principal' => $variant->nom, // Use 'nom' instead of 'attribut_principal'
                            'valeur' => $variant->valeur,
                            'image_url' => $variant->image_url ? $this->getFullImageUrl($variant->image_url) : null,
                            'stock' => (int) $variantStock,
                        ];
                    }),
                    'variants' => $this->parseVariants($product->variantes),
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
     * Parse variants into sizes and colors structure
     */
    private function parseVariants($variants)
    {
        $sizes = [];
        $colors = [];
        $sizeStocks = [];
        $colorStocks = [];

        foreach ($variants as $variant) {
            $stock = $variant->stocks->sum('qte_disponible') ?? 0;

            if ($variant->nom === 'couleur_taille' && strpos($variant->valeur, ' - ') !== false) {
                // Parse combined variant (e.g., "Rouge - M")
                [$color, $size] = explode(' - ', $variant->valeur, 2);

                // Add to sizes
                if (!isset($sizeStocks[$size])) {
                    $sizeStocks[$size] = 0;
                    $sizes[$size] = [
                        'id' => $size,
                        'value' => $size,
                        'stock' => 0
                    ];
                }
                $sizeStocks[$size] += $stock;
                $sizes[$size]['stock'] = $sizeStocks[$size];

                // Add to colors
                if (!isset($colorStocks[$color])) {
                    $colorStocks[$color] = 0;
                    $colors[$color] = [
                        'id' => $color,
                        'value' => $color,
                        'stock' => 0,
                        'image_url' => $variant->image_url
                    ];
                }
                $colorStocks[$color] += $stock;
                $colors[$color]['stock'] = $colorStocks[$color];

            } elseif ($variant->nom === 'taille') {
                // Individual size variant
                $sizes[$variant->valeur] = [
                    'id' => $variant->valeur,
                    'value' => $variant->valeur,
                    'stock' => $stock
                ];
            } elseif ($variant->nom === 'couleur') {
                // Individual color variant
                $colors[$variant->valeur] = [
                    'id' => $variant->valeur,
                    'value' => $variant->valeur,
                    'stock' => $stock,
                    'image_url' => $variant->image_url
                ];
            }
        }

        return [
            'sizes' => array_values($sizes),
            'colors' => array_values($colors)
        ];
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

            // Get the product's boutique warehouse
            $warehouse = \App\Models\Entrepot::where('boutique_id', $product->boutique_id)
                ->where('actif', true)
                ->first();

            // Calculate total stock from variants in the correct warehouse only
            $stockTotal = 0;
            if ($warehouse) {
                $stockTotal = $product->variantes->sum(function ($variant) use ($warehouse) {
                    return $variant->stocks->where('entrepot_id', $warehouse->id)->sum('qte_disponible');
                });
            }

            $transformedProduct = [
                'id' => $product->id,
                'titre' => $product->titre,
                'description' => $product->description,
                'copywriting' => $product->copywriting,
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
                        'url' => $this->getFullImageUrl($image->url),
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
                'variantes' => $product->variantes->map(function ($variant) use ($warehouse) {
                    // Calculate variant stock from the correct warehouse only
                    $stockOnHand = 0;
                    $stockReserved = 0;
                    if ($warehouse) {
                        $stockOnHand = $variant->stocks->where('entrepot_id', $warehouse->id)->sum('qte_disponible');
                        $stockReserved = $variant->stocks->where('entrepot_id', $warehouse->id)->sum('qte_reservee');
                    }
                    $stockAvailable = max(0, $stockOnHand - $stockReserved);

                    // Determine variant type and extract color/size
                    $type = 'other';
                    $color = null;
                    $size = null;

                    if ($variant->nom === 'couleur_taille' && strpos($variant->valeur, ' - ') !== false) {
                        $type = 'combination';
                        [$color, $size] = explode(' - ', $variant->valeur, 2);
                    } elseif (in_array(strtolower($variant->nom), ['couleur', 'color'])) {
                        $type = 'color';
                        $color = $variant->valeur;
                    } elseif (in_array(strtolower($variant->nom), ['taille', 'size'])) {
                        $type = 'size';
                        $size = $variant->valeur;
                    }

                    return [
                        'id' => $variant->id,
                        'type' => $type,
                        'value' => $variant->valeur,
                        'color' => $color,
                        'size' => $size,
                        'image_url' => $variant->image_url,
                        'stock_on_hand' => (int) $stockOnHand,
                        'stock_reserved' => (int) $stockReserved,
                        'stock_available' => (int) $stockAvailable,
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

    /**
     * Get full URL for image
     */
    private function getFullImageUrl(string $url): string
    {
        // If already a full URL, return as is
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // If starts with /, it's relative to domain
        if (str_starts_with($url, '/')) {
            return request()->getSchemeAndHttpHost() . $url;
        }

        // Otherwise, assume it's relative to storage
        return request()->getSchemeAndHttpHost() . '/storage/' . $url;
    }
}
