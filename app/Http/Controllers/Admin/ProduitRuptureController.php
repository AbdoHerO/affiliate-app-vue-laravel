<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProduitRuptureRequest;
use App\Http\Requests\Admin\UpdateProduitRuptureRequest;
use App\Http\Resources\ProduitRuptureResource;
use App\Models\Produit;
use App\Models\ProduitVariante;
use App\Models\ProduitRupture;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProduitRuptureController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of ruptures for a specific product.
     */
    public function index(Request $request, Produit $produit): JsonResponse
    {
        $this->authorize('view', $produit);

        $query = $produit->variantes()
            ->with(['ruptures' => function ($query) {
                $query->where('actif', true);
            }])
            ->has('ruptures');

        // Filter by active status
        if ($request->has('actif')) {
            $query->whereHas('ruptures', function ($q) use ($request) {
                $q->where('actif', $request->boolean('actif'));
            });
        }

        $variantes = $query->get();

        $ruptures = $variantes->flatMap(function ($variante) {
            return $variante->ruptures;
        });

        return response()->json([
            'data' => ProduitRuptureResource::collection($ruptures),
            'total' => $ruptures->count()
        ]);
    }

    /**
     * Store a newly created rupture alert.
     */
    public function store(StoreProduitRuptureRequest $request, Produit $produit): JsonResponse
    {
        $this->authorize('update', $produit);

        $variante = ProduitVariante::where('id', $request->variante_id)
            ->where('produit_id', $produit->id)
            ->firstOrFail();

        // Check if rupture already exists for this variant
        $existingRupture = ProduitRupture::where('variante_id', $variante->id)
            ->where('actif', true)
            ->first();

        if ($existingRupture) {
            return response()->json([
                'message' => __('messages.produit_ruptures.already_exists')
            ], 422);
        }

        $rupture = ProduitRupture::create([
            'variante_id' => $variante->id,
            'actif' => true,
        ]);

        return response()->json([
            'message' => __('messages.produit_ruptures.created'),
            'data' => new ProduitRuptureResource($rupture->load('variante.produit'))
        ], 201);
    }

    /**
     * Display the specified rupture.
     */
    public function show(Produit $produit, ProduitRupture $rupture): JsonResponse
    {
        $this->authorize('view', $produit);

        if ($rupture->variante->produit_id !== $produit->id) {
            abort(404);
        }

        return response()->json([
            'data' => new ProduitRuptureResource($rupture->load('variante.produit'))
        ]);
    }

    /**
     * Update the specified rupture.
     */
    public function update(UpdateProduitRuptureRequest $request, Produit $produit, ProduitRupture $rupture): JsonResponse
    {
        $this->authorize('update', $produit);

        if ($rupture->variante->produit_id !== $produit->id) {
            abort(404);
        }

        $rupture->update($request->validated());

        return response()->json([
            'message' => __('messages.produit_ruptures.updated'),
            'data' => new ProduitRuptureResource($rupture->load('variante.produit'))
        ]);
    }

    /**
     * Remove the specified rupture.
     */
    public function destroy(Produit $produit, ProduitRupture $rupture): JsonResponse
    {
        $this->authorize('update', $produit);

        if ($rupture->variante->produit_id !== $produit->id) {
            abort(404);
        }

        $rupture->delete();

        return response()->json([
            'message' => __('messages.produit_ruptures.deleted')
        ]);
    }

    /**
     * Resolve a rupture (mark as inactive).
     */
    public function resolve(Produit $produit, ProduitRupture $rupture): JsonResponse
    {
        $this->authorize('update', $produit);

        if ($rupture->variante->produit_id !== $produit->id) {
            abort(404);
        }

        $rupture->update(['actif' => false]);

        return response()->json([
            'message' => __('messages.produit_ruptures.resolved'),
            'data' => new ProduitRuptureResource($rupture->load('variante.produit'))
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
