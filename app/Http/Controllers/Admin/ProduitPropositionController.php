<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\ProduitProposition;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                'data' => [
                    'id' => $proposition->id,
                    'titre' => $proposition->titre,
                    'description' => $proposition->description,
                    'type' => $proposition->type,
                    'statut' => $proposition->statut,
                    'image_url' => $proposition->image_url,
                    'auteur' => $proposition->auteur ? [
                        'id' => $proposition->auteur->id,
                        'nom_complet' => $proposition->auteur->nom_complet,
                        'email' => $proposition->auteur->email,
                    ] : null,
                    'created_at' => $proposition->created_at,
                    'updated_at' => $proposition->updated_at,
                ]
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
        Log::info('ProduitPropositionController@uploadImage called', [
            'produit_id' => $produit->id,
            'proposition_id' => $proposition->id,
            'has_file' => $request->hasFile('file'),
            'files' => $request->allFiles(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length')
        ]);

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

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file received'
                ], 400);
            }

            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            // Use the public disk (no leading public/ in the relative path when specifying disk)
            $directory = 'products/' . $produit->id . '/propositions';
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

            $url = asset('storage/' . $path);

            // Update proposition with image URL
            $proposition->update(['image_url' => $url]);

            // Reload the proposition with author relationship
            $proposition->load('auteur:id,nom_complet,email');

            return response()->json([
                'success' => true,
                'message' => 'Proposition image uploaded successfully',
                'data' => [
                    'id' => $proposition->id,
                    'titre' => $proposition->titre,
                    'description' => $proposition->description,
                    'type' => $proposition->type,
                    'statut' => $proposition->statut,
                    'image_url' => $proposition->image_url,
                    'auteur' => $proposition->auteur ? [
                        'id' => $proposition->auteur->id,
                        'nom_complet' => $proposition->auteur->nom_complet,
                        'email' => $proposition->auteur->email,
                    ] : null,
                    'created_at' => $proposition->created_at,
                    'updated_at' => $proposition->updated_at,
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
