<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\ProduitVideo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProduitVideoController extends Controller
{
    /**
     * Display a listing of videos for a product.
     */
    public function index(Produit $produit): JsonResponse
    {
        $videos = $produit->videos()->orderBy('ordre')->get();

        return response()->json([
            'success' => true,
            'data' => $videos
        ]);
    }

    /**
     * Store a newly created video.
     */
    public function store(Request $request, Produit $produit): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|string|max:500',
            'titre' => 'nullable|string|max:255',
            'ordre' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $video = $produit->videos()->create([
                'url' => $request->url,
                'titre' => $request->titre,
                'ordre' => $request->ordre ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.video_created_successfully'),
                'data' => $video
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.video_creation_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified video.
     */
    public function update(Request $request, Produit $produit, ProduitVideo $video): JsonResponse
    {
        // Ensure video belongs to the product
        if ($video->produit_id !== $produit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Video not found for this product'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'url' => 'sometimes|required|string|max:500',
            'titre' => 'nullable|string|max:255',
            'ordre' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $video->update($request->only(['url', 'titre', 'ordre']));

            return response()->json([
                'success' => true,
                'message' => __('messages.video_updated_successfully'),
                'data' => $video
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.video_update_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified video.
     */
    public function destroy(Produit $produit, ProduitVideo $video): JsonResponse
    {
        // Ensure video belongs to the product
        if ($video->produit_id !== $produit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Video not found for this product'
            ], 404);
        }

        try {
            $video->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.video_deleted_successfully')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.video_deletion_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update video order.
     */
    public function bulkSort(Request $request, Produit $produit): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|string|exists:produit_videos,id',
            'items.*.ordre' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->items as $item) {
                $video = ProduitVideo::where('id', $item['id'])
                    ->where('produit_id', $produit->id)
                    ->first();
                
                if ($video) {
                    $video->update(['ordre' => $item['ordre']]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => __('messages.videos_sorted_successfully')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.videos_sort_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
