<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBoutiqueRequest;
use App\Http\Requests\Admin\UpdateBoutiqueRequest;
use App\Http\Resources\BoutiqueResource;
use App\Models\Boutique;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class BoutiqueController extends Controller
{
    /**
     * Display a listing of boutiques
     */
    public function index(Request $request)
    {
        $query = Boutique::with('proprietaire:id,nom_complet,email');

        // Search functionality
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('dir', 'desc');
        
        // Validate sort field
        $allowedSortFields = ['nom', 'slug', 'statut', 'created_at', 'updated_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        
        // Validate sort direction
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $boutiques = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => __('messages.boutiques_retrieved_successfully'),
            'data' => BoutiqueResource::collection($boutiques->items()),
            'meta' => [
                'current_page' => $boutiques->currentPage(),
                'last_page' => $boutiques->lastPage(),
                'per_page' => $boutiques->perPage(),
                'total' => $boutiques->total(),
            ]
        ]);
    }

    /**
     * Store a newly created boutique
     */
    public function store(StoreBoutiqueRequest $request)
    {
        $data = $request->validated();

        // Auto-generate slug if empty
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['nom']);
        }

        $boutique = Boutique::create($data);
        $boutique->load('proprietaire:id,nom_complet,email');

        return response()->json([
            'success' => true,
            'message' => __('messages.boutique_created_successfully'),
            'data' => new BoutiqueResource($boutique)
        ], 201);
    }

    /**
     * Display the specified boutique
     */
    public function show(string $id)
    {
        try {
            $boutique = Boutique::with('proprietaire:id,nom_complet,email')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new BoutiqueResource($boutique)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.boutique_not_found')
            ], 404);
        }
    }

    /**
     * Update the specified boutique
     */
    public function update(UpdateBoutiqueRequest $request, string $id)
    {
        $boutique = Boutique::findOrFail($id);
        $data = $request->validated();

        // Auto-generate slug if empty
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['nom'], $id);
        }

        $boutique->update($data);
        $boutique->load('proprietaire:id,nom_complet,email');

        return response()->json([
            'success' => true,
            'message' => __('messages.boutique_updated_successfully'),
            'data' => new BoutiqueResource($boutique)
        ]);
    }

    /**
     * Remove the specified boutique
     */
    public function destroy(string $id)
    {
        try {
            $boutique = Boutique::findOrFail($id);
            
            // Check for related records that might prevent deletion
            $hasProducts = $boutique->produits()->exists();
            $hasOrders = $boutique->commandes()->exists();
            
            if ($hasProducts || $hasOrders) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.cannot_delete_boutique_with_orders'),
                    'reason' => $hasProducts ? 'products' : 'orders'
                ], Response::HTTP_CONFLICT);
            }
            
            $boutique->delete();

            return response()->json([
                'success' => true,
                'message' => __('messages.boutique_deleted_successfully')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.server_error'),
                'error' => $e->getMessage()
            ], Response::HTTP_CONFLICT);
        }
    }

    /**
     * Generate a unique slug from the given name
     */
    private function generateUniqueSlug(string $name, string $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        $query = Boutique::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            
            $query = Boutique::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
