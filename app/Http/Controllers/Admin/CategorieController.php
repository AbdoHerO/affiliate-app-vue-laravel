<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategorieRequest;
use App\Http\Requests\Admin\UpdateCategorieRequest;
use App\Models\Categorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategorieController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Categorie::query();

            // Handle soft delete filtering
            $includeDeleted = $request->get('include_deleted', 'active'); // active, trashed, all
            switch ($includeDeleted) {
                case 'trashed':
                    $query->onlyTrashed();
                    break;
                case 'all':
                    $query->withTrashed();
                    break;
                case 'active':
                default:
                    // Default behavior - only active (non-deleted) records
                    break;
            }

            // Search by name
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where('nom', 'like', "%{$search}%");
            }

            // Filter by status
            if ($request->filled('status')) {
                $status = $request->get('status') === 'true';
                $query->where('actif', $status);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'ordre');
            $sortDirection = $request->get('sort_direction', 'asc');
            
            if (in_array($sortBy, ['nom', 'ordre', 'actif'])) {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $categories = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => __('messages.categories_retrieved_success'),
                'data' => $categories->items(),
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'last_page' => $categories->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.categories_retrieve_error'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategorieRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Generate slug if not provided
            if (empty($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['nom']);
            }

            $categorie = Categorie::create($validated);

            return response()->json([
                'success' => true,
                'message' => __('messages.category_created_success'),
                'data' => $categorie
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.category_creation_error'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified category.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $categorie = Categorie::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => __('messages.category_retrieved_success'),
                'data' => $categorie
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.category_not_found'),
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategorieRequest $request, string $id): JsonResponse
    {
        try {
            $categorie = Categorie::findOrFail($id);
            $validated = $request->validated();

            // Generate slug if not provided
            if (empty($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['nom']);
            }

            $categorie->update($validated);

            return response()->json([
                'success' => true,
                'message' => __('messages.category_updated_success'),
                'data' => $categorie->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.category_update_error'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete the specified category.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $categorie = Categorie::findOrFail($id);
            $categorie->delete(); // This will now be a soft delete

            return response()->json([
                'success' => true,
                'message' => __('messages.category_deleted_success')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.category_deletion_error'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft deleted category.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $categorie = Categorie::withTrashed()->findOrFail($id);

            if (!$categorie->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.category_not_deleted')
                ], 400);
            }

            $categorie->restore();

            return response()->json([
                'success' => true,
                'message' => __('messages.category_restored_success'),
                'data' => $categorie
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.category_restore_error'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete the specified category.
     */
    public function forceDelete(string $id): JsonResponse
    {
        try {
            $categorie = Categorie::withTrashed()->findOrFail($id);

            // Check if category has products before permanent deletion
            if ($categorie->produits()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.category_has_products_error')
                ], 422);
            }

            $categorie->forceDelete();

            return response()->json([
                'success' => true,
                'message' => __('messages.category_permanently_deleted')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.category_permanent_deletion_error'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle the active status of a category.
     */
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            $categorie = Categorie::findOrFail($id);
            $categorie->update(['actif' => !$categorie->actif]);

            return response()->json([
                'success' => true,
                'message' => $categorie->actif 
                    ? __('messages.category_activated_success') 
                    : __('messages.category_deactivated_success'),
                'data' => $categorie->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.category_status_toggle_error'),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
