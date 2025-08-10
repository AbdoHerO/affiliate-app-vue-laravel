<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\ProduitProposition;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProduitPropositionController extends Controller
{
    /**
     * Display a listing of propositions for a specific product.
     */
    public function index(Produit $produit): JsonResponse
    {
        try {
            $propositions = $produit->propositions()
                ->with('auteur:id,nom_complet,email')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $propositions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching propositions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created proposition.
     */
    public function store(Request $request, Produit $produit): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|string|in:nouveau,modification,suppression',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $proposition = ProduitProposition::create([
                'produit_id' => $produit->id,
                'auteur_id' => $request->user()->id,
                'titre' => $request->titre,
                'description' => $request->description,
                'type' => $request->type,
                'statut' => 'en_attente'
            ]);

            $proposition->load('auteur:id,nom_complet,email');

            return response()->json([
                'success' => true,
                'message' => 'Proposition created successfully',
                'data' => $proposition
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating proposition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified proposition.
     */
    public function update(Request $request, Produit $produit, ProduitProposition $proposition): JsonResponse
    {
        // Ensure proposition belongs to the product
        if ($proposition->produit_id !== $produit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Proposition not found for this product'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'type' => 'sometimes|required|string|in:nouveau,modification,suppression',
            'statut' => 'sometimes|required|string|in:en_attente,approuve,refuse',
            'notes_admin' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $proposition->update($request->only([
                'titre', 'description', 'type', 'statut', 'notes_admin'
            ]));

            $proposition->load('auteur:id,nom_complet,email');

            return response()->json([
                'success' => true,
                'message' => 'Proposition updated successfully',
                'data' => $proposition
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating proposition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload image for the specified proposition.
     */
    public function uploadImage(Request $request, Produit $produit, ProduitProposition $proposition): JsonResponse
    {
        // Ensure proposition belongs to the product
        if ($proposition->produit_id !== $produit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Proposition not found for this product'
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
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('public/products/' . $produit->id . '/propositions', $filename);
            $url = Storage::url($path);

            // Update proposition with image URL
            $proposition->update(['image_url' => $url]);

            return response()->json([
                'success' => true,
                'message' => 'Proposition image uploaded successfully',
                'data' => [
                    'image_url' => $url,
                    'proposition' => $proposition
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Proposition image upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified proposition.
     */
    public function destroy(Produit $produit, ProduitProposition $proposition): JsonResponse
    {
        // Ensure proposition belongs to the product
        if ($proposition->produit_id !== $produit->id) {
            return response()->json([
                'success' => false,
                'message' => 'Proposition not found for this product'
            ], 404);
        }

        try {
            // Delete image file if exists
            if ($proposition->image_url) {
                $imagePath = str_replace('/storage/', 'public/', $proposition->image_url);
                Storage::delete($imagePath);
            }

            $proposition->delete();

            return response()->json([
                'success' => true,
                'message' => 'Proposition deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting proposition',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
