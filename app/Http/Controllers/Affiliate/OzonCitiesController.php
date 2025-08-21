<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\ShippingCity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OzonCitiesController extends Controller
{
    /**
     * Get cities list for affiliates (read-only)
     * Returns only id and name fields for dropdown usage
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ShippingCity::where('active', true)
                ->select('city_id', 'name')
                ->orderBy('name');

            // Search functionality
            if ($request->has('q') && !empty($request->q)) {
                $searchTerm = $request->q;
                $query->where('name', 'LIKE', "%{$searchTerm}%");
            }

            // Pagination
            $perPage = min($request->get('per_page', 50), 100); // Max 100 items
            $cities = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $cities->items(),
                'meta' => [
                    'current_page' => $cities->currentPage(),
                    'last_page' => $cities->lastPage(),
                    'per_page' => $cities->perPage(),
                    'total' => $cities->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des villes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
