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
     * Returns id, name and prices fields for dropdown usage and delivery calculation
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ShippingCity::where('active', true)
                ->select('city_id', 'name', 'prices')
                ->orderBy('name');

            // Search functionality
            if ($request->has('q') && !empty($request->q)) {
                $searchTerm = $request->q;
                $query->where('name', 'LIKE', "%{$searchTerm}%");
            }

            // For affiliate dropdown, return ALL cities without pagination
            // Frontend will handle filtering with VAutocomplete
            $cities = $query->get();

            return response()->json([
                'success' => true,
                'data' => $cities->toArray(),
                'meta' => [
                    'total' => $cities->count(),
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
