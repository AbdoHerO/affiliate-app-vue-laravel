<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProduitRequest;
use App\Http\Requests\Admin\UpdateProduitRequest;
use App\Http\Resources\ProduitResource;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProduitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Produit::with(['boutique', 'categorie', 'images']);

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

        // Filter by status
        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        
        $allowedSorts = ['titre', 'prix_vente', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50]) ? $perPage : 15;

        $produits = $query->paginate($perPage);

        return ProduitResource::collection($produits);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProduitRequest $request): JsonResponse
    {
        try {
            $produit = Produit::create($request->validated());
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
        $produit->load(['boutique', 'categorie', 'images' => function ($query) {
            $query->orderBy('ordre', 'asc');
        }]);

        return new ProduitResource($produit);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProduitRequest $request, Produit $produit): JsonResponse
    {
        try {
            $produit->update($request->validated());
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
