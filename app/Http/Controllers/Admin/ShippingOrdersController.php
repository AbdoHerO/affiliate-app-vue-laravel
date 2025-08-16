<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ShippingOrdersController extends Controller
{
    /**
     * Display a listing of shipping orders (orders with parcels).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Commande::with([
            'boutique:id,nom',
            'affiliate:id,nom_complet,email', // Changed from affilie.utilisateur to affiliate
            'client:id,nom_complet,telephone',
            'adresse:id,ville,adresse',
            'shippingParcel'
        ])
        ->whereHas('shippingParcel');

        // Apply filters
        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->whereHas('client', function ($clientQuery) use ($search) {
                    $clientQuery->where('nom_complet', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%");
                })
                ->orWhereHas('shippingParcel', function ($parcelQuery) use ($search) {
                    $parcelQuery->where('tracking_number', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('status')) {
            $query->whereHas('shippingParcel', function ($parcelQuery) use ($request) {
                $parcelQuery->where('status', $request->get('status'));
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        // Sorting
        $sortBy = $request->get('sort', 'updated_at');
        $sortDir = $request->get('dir', 'desc');

        if ($sortBy === 'tracking_number') {
            $query->join('shipping_parcels', 'commandes.id', '=', 'shipping_parcels.commande_id')
                  ->orderBy('shipping_parcels.tracking_number', $sortDir)
                  ->select('commandes.*');
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        // Pagination
        $perPage = $request->get('perPage', 15);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Display the specified shipping order.
     */
    public function show(string $id): JsonResponse
    {
        $order = Commande::with([
            'boutique',
            'affiliate', // Changed from affilie.utilisateur to affiliate
            'client',
            'adresse',
            'articles.produit.images',
            'articles.variante',
            'shippingParcel'
        ])->findOrFail($id);

        if (!$order->shippingParcel) {
            return response()->json([
                'success' => false,
                'message' => 'This order has no shipping parcel',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Refresh tracking for a single parcel
     */
    public function refreshTracking(Request $request): JsonResponse
    {
        $request->validate([
            'tracking_number' => 'required|string'
        ]);

        try {
            $trackingNumber = $request->tracking_number;

            // Find the shipping parcel
            $parcel = \App\Models\ShippingParcel::where('tracking_number', $trackingNumber)
                ->where('provider', 'ozonexpress')
                ->first();

            if (!$parcel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parcel not found'
                ], 404);
            }

            // Use OzonExpress service to track
            $ozonService = app(\App\Services\OzonExpressService::class);
            $result = $ozonService->track($trackingNumber);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tracking updated successfully',
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to update tracking'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error refreshing tracking', [
                'tracking_number' => $request->tracking_number,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error refreshing tracking: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh tracking for multiple parcels
     */
    public function refreshTrackingBulk(Request $request): JsonResponse
    {
        $request->validate([
            'tracking_numbers' => 'required|array',
            'tracking_numbers.*' => 'required|string'
        ]);

        try {
            $trackingNumbers = $request->tracking_numbers;
            $ozonService = app(\App\Services\OzonExpressService::class);

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($trackingNumbers as $trackingNumber) {
                try {
                    $result = $ozonService->track($trackingNumber);

                    if ($result['success']) {
                        $successCount++;
                        $results[] = [
                            'tracking_number' => $trackingNumber,
                            'success' => true,
                            'data' => $result['data']
                        ];
                    } else {
                        $errorCount++;
                        $results[] = [
                            'tracking_number' => $trackingNumber,
                            'success' => false,
                            'message' => $result['message']
                        ];
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'tracking_number' => $trackingNumber,
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Tracking updated: {$successCount} successful, {$errorCount} failed",
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total' => count($trackingNumbers),
                        'success' => $successCount,
                        'errors' => $errorCount
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in bulk tracking refresh', [
                'tracking_numbers' => $request->tracking_numbers,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error in bulk tracking refresh: ' . $e->getMessage()
            ], 500);
        }
    }
}
