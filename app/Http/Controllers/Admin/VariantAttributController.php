<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VariantAttribut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VariantAttributController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = VariantAttribut::query();

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

            // Search by code or nom
            if ($request->filled('q')) {
                $search = $request->get('q');
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'LIKE', "%{$search}%")
                      ->orWhere('nom', 'LIKE', "%{$search}%");
                });
            }

            // Filter by actif status
            if ($request->has('actif') && $request->get('actif') !== '') {
                $query->where('actif', $request->boolean('actif'));
            }

            // Sorting
            $sortBy = $request->get('sort', 'nom');
            $sortDirection = $request->get('dir', 'asc');

            $allowedSorts = ['code', 'nom', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDirection === 'desc' ? 'desc' : 'asc');
            } else {
                $query->orderBy('nom', 'asc');
            }

            // Pagination
            $perPage = $request->get('perPage', 15);
            $perPage = in_array((int)$perPage, [10, 15, 25, 50, 100]) ? (int)$perPage : 15;

            $attributs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $attributs->items(),
                'meta' => [
                    'current_page' => $attributs->currentPage(),
                    'last_page' => $attributs->lastPage(),
                    'per_page' => $attributs->perPage(),
                    'total' => $attributs->total(),
                    'from' => $attributs->firstItem(),
                    'to' => $attributs->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching variant attributes'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|alpha_dash|max:50|unique:variant_attributs,code',
                'nom' => 'required|string|max:100',
                'actif' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attribut = VariantAttribut::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Variant attribute created successfully',
                'data' => $attribut
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating variant attribute'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(VariantAttribut $variantAttribut): JsonResponse
    {
        try {
            $variantAttribut->load('valeursActives');

            return response()->json([
                'success' => true,
                'data' => $variantAttribut
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching variant attribute'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VariantAttribut $variantAttribut): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|alpha_dash|max:50|unique:variant_attributs,code,' . $variantAttribut->id,
                'nom' => 'required|string|max:100',
                'actif' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $variantAttribut->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Variant attribute updated successfully',
                'data' => $variantAttribut
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating variant attribute'
            ], 500);
        }
    }

    /**
     * Soft delete the specified resource.
     */
    public function destroy(VariantAttribut $variantAttribut): JsonResponse
    {
        try {
            $variantAttribut->delete(); // This will now be a soft delete

            return response()->json([
                'success' => true,
                'message' => 'Variant attribute deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting variant attribute'
            ], 500);
        }
    }

    /**
     * Restore a soft deleted variant attribute.
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $variantAttribut = VariantAttribut::withTrashed()->findOrFail($id);

            if (!$variantAttribut->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Variant attribute is not deleted'
                ], 400);
            }

            $variantAttribut->restore();

            return response()->json([
                'success' => true,
                'message' => 'Variant attribute restored successfully',
                'data' => $variantAttribut
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error restoring variant attribute'
            ], 500);
        }
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(string $id): JsonResponse
    {
        try {
            $variantAttribut = VariantAttribut::withTrashed()->findOrFail($id);

            // Check if attribute has values before permanent deletion
            if ($variantAttribut->valeurs()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot permanently delete attribute that has values'
                ], 409);
            }

            $variantAttribut->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Variant attribute permanently deleted'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error permanently deleting variant attribute'
            ], 500);
        }
    }

    /**
     * Toggle the actif status of the attribute
     */
    public function toggleStatus(VariantAttribut $variantAttribut): JsonResponse
    {
        try {
            $variantAttribut->update(['actif' => !$variantAttribut->actif]);

            return response()->json([
                'success' => true,
                'message' => 'Attribute status updated successfully',
                'data' => $variantAttribut
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating attribute status'
            ], 500);
        }
    }
}
