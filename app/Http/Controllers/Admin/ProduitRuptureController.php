<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProduitRuptureRequest;
use App\Http\Requests\Admin\UpdateProduitRuptureRequest;
use App\Http\Resources\ProduitRuptureResource;
use App\Models\Produit;
use App\Models\ProduitVariante;
use App\Models\ProduitRupture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProduitRuptureController extends Controller
{
    /**
     * Display a listing of ruptures for a specific product.
     */
    public function index(Request $request, Produit $produit): JsonResponse
    {
        // Get all ruptures for this product
        $query = $produit->ruptures();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $ruptures = $query->orderBy('started_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $ruptures->map(function ($rupture) {
                return [
                    'id' => $rupture->id,
                    'variante_id' => $rupture->variante_id,
                    'motif' => $rupture->motif,
                    'started_at' => $rupture->started_at,
                    'expected_restock_at' => $rupture->expected_restock_at,
                    'active' => $rupture->active,
                    'resolved_at' => $rupture->resolved_at,
                    'created_at' => $rupture->created_at,
                    'updated_at' => $rupture->updated_at,
                ];
            }),
            'total' => $ruptures->count()
        ]);
    }

    /**
     * Store a newly created rupture alert.
     */
    public function store(StoreProduitRuptureRequest $request, Produit $produit): JsonResponse
    {
        try {
            $validated = $request->validated();

            // If variante_id is provided, validate it belongs to this product
            if (!empty($validated['variante_id'])) {
                $variante = ProduitVariante::where('id', $validated['variante_id'])
                    ->where('produit_id', $produit->id)
                    ->first();

                if (!$variante) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Variant not found for this product'
                    ], 404);
                }

                // Check if rupture already exists for this variant
                $existingRupture = ProduitRupture::where('variante_id', $variante->id)
                    ->where('active', true)
                    ->first();

                if ($existingRupture) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Active rupture already exists for this variant'
                    ], 422);
                }
            }

            $rupture = ProduitRupture::create([
                'produit_id' => $produit->id,
                'variante_id' => $validated['variante_id'] ?? null,
                'motif' => $validated['motif'],
                'started_at' => $validated['started_at'],
                'expected_restock_at' => $validated['expected_restock_at'] ?? null,
                'active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock issue reported successfully',
                'data' => [
                    'id' => $rupture->id,
                    'variante_id' => $rupture->variante_id,
                    'motif' => $rupture->motif,
                    'started_at' => $rupture->started_at,
                    'expected_restock_at' => $rupture->expected_restock_at,
                    'active' => $rupture->active,
                    'resolved_at' => $rupture->resolved_at,
                    'created_at' => $rupture->created_at,
                    'updated_at' => $rupture->updated_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock issue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified rupture.
     */
    public function show(Produit $produit, ProduitRupture $rupture): JsonResponse
    {
        if ($rupture->produit_id !== $produit->id) {
            abort(404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $rupture->id,
                'variante_id' => $rupture->variante_id,
                'motif' => $rupture->motif,
                'started_at' => $rupture->started_at,
                'expected_restock_at' => $rupture->expected_restock_at,
                'active' => $rupture->active,
                'resolved_at' => $rupture->resolved_at,
                'created_at' => $rupture->created_at,
                'updated_at' => $rupture->updated_at,
            ]
        ]);
    }

    /**
     * Update the specified rupture.
     */
    public function update(UpdateProduitRuptureRequest $request, Produit $produit, ProduitRupture $rupture): JsonResponse
    {
        if ($rupture->produit_id !== $produit->id) {
            abort(404);
        }

        $rupture->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Stock issue updated successfully',
            'data' => [
                'id' => $rupture->id,
                'variante_id' => $rupture->variante_id,
                'motif' => $rupture->motif,
                'started_at' => $rupture->started_at,
                'expected_restock_at' => $rupture->expected_restock_at,
                'active' => $rupture->active,
                'resolved_at' => $rupture->resolved_at,
                'created_at' => $rupture->created_at,
                'updated_at' => $rupture->updated_at,
            ]
        ]);
    }

    /**
     * Remove the specified rupture.
     */
    public function destroy(Produit $produit, ProduitRupture $rupture): JsonResponse
    {
        if ($rupture->produit_id !== $produit->id) {
            abort(404);
        }

        $rupture->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock issue deleted successfully'
        ]);
    }

    /**
     * Resolve a rupture (mark as inactive).
     */
    public function resolve(Produit $produit, ProduitRupture $rupture): JsonResponse
    {
        if ($rupture->produit_id !== $produit->id) {
            abort(404);
        }

        $rupture->update([
            'active' => false,
            'resolved_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stock issue resolved successfully',
            'data' => [
                'id' => $rupture->id,
                'variante_id' => $rupture->variante_id,
                'motif' => $rupture->motif,
                'started_at' => $rupture->started_at,
                'expected_restock_at' => $rupture->expected_restock_at,
                'active' => $rupture->active,
                'resolved_at' => $rupture->resolved_at,
                'created_at' => $rupture->created_at,
                'updated_at' => $rupture->updated_at,
            ]
        ]);
    }

    /**
     * Get all active ruptures across all products (for admin dashboard).
     */
    public function getAllActive(Request $request): JsonResponse
    {
        $query = ProduitRupture::with(['variante.produit.boutique'])
            ->where('actif', true);

        // Filter by boutique
        if ($request->has('boutique_id')) {
            $query->whereHas('variante.produit.boutique', function ($q) use ($request) {
                $q->where('id', $request->boutique_id);
            });
        }

        $ruptures = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => ProduitRuptureResource::collection($ruptures),
            'total' => $ruptures->count()
        ]);
    }
}
