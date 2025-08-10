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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
     * Upload and store a new image file.
     */
    public function upload(Request $request, Produit $produit): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'alt_text' => 'nullable|string|max:255',
        ], [
            'file.required' => __('validation.required', ['attribute' => __('validation.attributes.file')]),
            'file.file' => __('validation.file', ['attribute' => __('validation.attributes.file')]),
            'file.image' => __('validation.image', ['attribute' => __('validation.attributes.file')]),
            'file.mimes' => __('validation.mimes', ['attribute' => __('validation.attributes.file'), 'values' => 'jpeg, png, jpg, gif, webp']),
            'file.max' => __('validation.max.file', ['attribute' => __('validation.attributes.file'), 'max' => 5120]),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');

            // Create directory path
            $directory = "products/{$produit->id}/images";

            // Store the file
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs($directory, $filename, 'public');

            // Generate the full URL
            $url = asset('storage/' . $path);

            // Get the next order
            $maxOrder = $produit->images()->max('ordre') ?? -1;
            $ordre = $maxOrder + 1;

            // Create the image record
            $image = ProduitImage::create([
                'produit_id' => $produit->id,
                'url' => $url,
                'alt_text' => $request->input('alt_text', ''),
                'ordre' => $ordre,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.produit_images.uploaded_successfully'),
                'data' => new ProduitImageResource($image),
                'url' => $url,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produit_images.upload_failed'),
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

            // Delete the physical file if it's stored locally
            if ($image->url && str_contains($image->url, '/storage/')) {
                $filePath = str_replace('/storage/', 'public/', parse_url($image->url, PHP_URL_PATH));
                Storage::delete($filePath);
            }

            $image->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.produit_images.deleted_successfully'),
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.produit_images.deletion_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
