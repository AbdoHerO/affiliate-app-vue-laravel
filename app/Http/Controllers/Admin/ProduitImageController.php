<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProduitImageRequest;
use App\Http\Resources\ProduitImageResource;
use App\Models\Produit;
use App\Models\ProduitImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProduitImageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Produit $produit): AnonymousResourceCollection
    {
        $images = $produit->images()->orderBy('ordre', 'asc')->get();
        return ProduitImageResource::collection($images);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProduitImageRequest $request, Produit $produit): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // If no order is specified, set it to the next available order
            if (!isset($validated['ordre'])) {
                $maxOrder = $produit->images()->max('ordre') ?? -1;
                $validated['ordre'] = $maxOrder + 1;
            }
            
            $validated['produit_id'] = $produit->id;
            
            $image = ProduitImage::create($validated);

            return response()->json([
                'success' => true,
                'message' => __('messages.produit_images.created_successfully'),
                'data' => new ProduitImageResource($image),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produit_images.creation_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update the order of images.
     */
    public function bulkSort(StoreProduitImageRequest $request, Produit $produit): JsonResponse
    {
        try {
            $validated = $request->validated();
            $items = $validated['items'];

            DB::transaction(function () use ($items, $produit) {
                foreach ($items as $item) {
                    ProduitImage::where('id', $item['id'])
                        ->where('produit_id', $produit->id)
                        ->update(['ordre' => $item['ordre']]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => __('messages.produit_images.sorted_successfully'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produit_images.sort_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Produit $produit, ProduitImage $image): JsonResponse
    {
        try {
            // Ensure the image belongs to the product
            if ($image->produit_id !== $produit->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.produit_images.not_found'),
                ], 404);
            }

            $image->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.produit_images.deleted_successfully'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produit_images.deletion_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
