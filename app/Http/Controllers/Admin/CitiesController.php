<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingCity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CitiesController extends Controller
{
    /**
     * Get cached list of cities, refresh if cache miss or forced.
     */
    public function index(Request $request): JsonResponse
    {
        $forceRefresh = $request->boolean('refresh', false);
        $cacheKey = 'ozonexpress_cities';
        $cacheDuration = 24 * 60 * 60; // 24 hours

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $cities = Cache::remember($cacheKey, $cacheDuration, function () {
            return $this->fetchAndCacheCities();
        });

        if ($cities === null) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cities from OzonExpress',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $cities,
        ]);
    }

    /**
     * Fetch cities from OzonExpress API and cache them in database.
     */
    private function fetchAndCacheCities(): ?array
    {
        try {
            $response = Http::get('https://api.ozonexpress.ma/cities');

            if (!$response->successful()) {
                return null;
            }

            $citiesData = $response->json();

            // Clear existing cities for this provider
            ShippingCity::where('provider', 'ozonexpress')->delete();

            // Store cities in database
            foreach ($citiesData as $cityData) {
                ShippingCity::create([
                    'provider' => 'ozonexpress',
                    'city_id' => $cityData['id'] ?? $cityData['ID'],
                    'ref' => $cityData['ref'] ?? $cityData['REF'],
                    'name' => $cityData['name'] ?? $cityData['NAME'],
                    'prices' => [
                        'delivery' => $cityData['delivery_price'] ?? $cityData['DELIVERY_PRICE'] ?? 0,
                        'return' => $cityData['return_price'] ?? $cityData['RETURN_PRICE'] ?? 0,
                        'refused' => $cityData['refused_price'] ?? $cityData['REFUSED_PRICE'] ?? 0,
                    ],
                ]);
            }

            // Return formatted data for frontend
            return ShippingCity::where('provider', 'ozonexpress')
                ->select('city_id', 'ref', 'name', 'prices')
                ->orderBy('name')
                ->get()
                ->toArray();

        } catch (\Exception $e) {
            Log::error('Failed to fetch cities from OzonExpress: ' . $e->getMessage());
            return null;
        }
    }
}
