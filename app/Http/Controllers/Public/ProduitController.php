<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProduitResource;
use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProduitController extends Controller
{
    /**
     * Get a product by slug for public viewing
     */
    public function show(Request $request, string $slugOrId): JsonResponse
    {
        try {
            // Try to find by slug first, then by ID as fallback
            $produit = Produit::where('slug', $slugOrId)
                ->orWhere('id', $slugOrId)
                ->where('actif', true) // Only show active products
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
                        $query->where('actif', true)->orderBy('nom', 'asc');
                    },
                    'propositions' => function ($query) {
                        $query->where('statut', 'approuve')->orderBy('created_at', 'desc');
                    },
                    'ruptures' => function ($query) {
                        $query->where('active', true)->orderBy('started_at', 'desc');
                    }
                ])
                ->first();

            if (!$produit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new PublicProduitResource($produit)
            ])->header('Cache-Control', 'public, max-age=300');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving product'
            ], 500);
        }
    }
}
