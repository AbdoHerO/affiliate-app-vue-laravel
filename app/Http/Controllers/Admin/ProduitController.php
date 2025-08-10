<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProduitRequest;
use App\Http\Requests\Admin\UpdateProduitRequest;
use App\Http\Resources\ProduitResource;
use App\Models\Produit;
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

        // Search by title or slug
        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%");
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

            $produit = Produit::create($validated);
            $produit->load(['boutique', 'categorie', 'images']);

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

            $produit->update($validated);
            $produit->load(['boutique', 'categorie', 'images']);

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
     * Remove the specified resource from storage.
     */
    public function destroy(Produit $produit): JsonResponse
    {
        try {
            // Check for related records that would prevent deletion
            $constraints = $this->checkDeleteConstraints($produit);
            
            if (!empty($constraints)) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.produits.delete_failed_constraints'),
                    'constraints' => $constraints,
                ], 409);
            }

            $produit->delete();

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
}
