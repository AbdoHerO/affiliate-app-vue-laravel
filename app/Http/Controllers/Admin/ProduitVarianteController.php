<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\ProduitVariante;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProduitVarianteController extends Controller
{
    /**
     * Display a listing of variants for a product.
     */
    public function index(Produit $produit): JsonResponse
    {
        $variantes = $produit->variantes()->orderBy('nom')->orderBy('valeur')->get();

        return response()->json([
            'success' => true,
            'data' => $variantes
        ]);
    }

    /**
     * Store a newly created variant.
     */
    public function store(Request $request, Produit $produit): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'valeur' => 'required|string|max:255',
            'prix_vente_variante' => 'nullable|numeric|min:0',
            'sku_variante' => 'nullable|string|max:100|unique:produit_variantes,sku_variante',
            'actif' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $variante = $produit->variantes()->create([
                'nom' => $request->nom,
                'valeur' => $request->valeur,
                'prix_vente_variante' => $request->prix_vente_variante,
                'sku_variante' => $request->sku_variante,
                'actif' => $request->boolean('actif', true)
            ]);

            return response()->json([
                'success' => true,
                'message' => __('messages.variant_created_successfully'),
                'data' => $variante
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.variant_creation_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified variant.
     */
    public function show(Produit $produit, ProduitVariante $variante): JsonResponse
    {
        // Ensure variant belongs to the product
        if ($variante->produit_id !== $produit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found for this product'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $variante
        ]);
    }

    /**
     * Update the specified variant.
     */
    public function update(Request $request, Produit $produit, ProduitVariante $variante): JsonResponse
    {
        // Ensure variant belongs to the product
        if ($variante->produit_id !== $produit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found for this product'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:255',
            'valeur' => 'sometimes|required|string|max:255',
            'prix_vente_variante' => 'nullable|numeric|min:0',
            'sku_variante' => 'nullable|string|max:100|unique:produit_variantes,sku_variante,' . $variante->id,
            'actif' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $variante->update($request->only([
                'nom', 'valeur', 'prix_vente_variante', 'sku_variante', 'actif'
            ]));

            return response()->json([
                'success' => true,
                'message' => __('messages.variant_updated_successfully'),
                'data' => $variante
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.variant_update_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified variant.
     */
    public function destroy(Produit $produit, ProduitVariante $variante): JsonResponse
    {
        // Ensure variant belongs to the product
        if ($variante->produit_id !== $produit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found for this product'
            ], 404);
        }

        try {
            $variante->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.variant_deleted_successfully')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.variant_deletion_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload image for the specified variant.
     */
    public function uploadImage(Request $request, Produit $produit, ProduitVariante $variante): JsonResponse
    {
        Log::info('ProduitVarianteController@uploadImage called', [
            'produit_id' => $produit->id,
            'variante_id' => $variante->id,
            'has_file' => $request->hasFile('file'),
            'files' => $request->allFiles(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length')
        ]);

        // Ensure variant belongs to the product
        if ($variante->produit_id !== $produit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found for this product'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file received'
                ], 400);
            }

            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            // Use the public disk consistently (same as main product images controller)
            // Directory must NOT be prefixed with 'public/' when passing disk 'public'
            $directory = 'products/' . $produit->id . '/variants';
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $path = $file->storeAs($directory, $filename, 'public');

            if (!$path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store file'
                ], 500);
            }

            // Build accessible URL (storage symlink required)
            $url = asset('storage/' . $path);

            // Update variant with image URL
            $variante->update(['image_url' => $url]);

            return response()->json([
                'success' => true,
                'message' => __('messages.variant_image_uploaded_successfully'),
                'data' => [
                    'image_url' => $url,
                    'variant' => $variante
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.variant_image_upload_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
