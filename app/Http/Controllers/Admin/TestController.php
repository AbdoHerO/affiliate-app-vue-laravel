<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Services\OzonExpressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    /**
     * Test OzonExpress integration
     */
    public function testOzonExpress(Request $request): JsonResponse
    {
        $ozonService = new OzonExpressService();
        
        // Get a sample confirmed order without shipping parcel
        $order = Commande::with(['client', 'adresse', 'articles.produit'])
            ->where('statut', 'confirmee')
            ->whereDoesntHave('shippingParcel')
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'No confirmed orders available for testing',
                'suggestion' => 'Create a confirmed order first or change an existing order status to "confirmee"'
            ]);
        }

        $result = $ozonService->addParcel($order);

        return response()->json([
            'success' => true,
            'message' => 'OzonExpress test completed',
            'ozon_enabled' => $ozonService->isEnabled(),
            'test_order_id' => $order->id,
            'result' => $result,
        ]);
    }

    /**
     * Test bulk operations
     */
    public function testBulkOperations(Request $request): JsonResponse
    {
        // Get some confirmed orders
        $orders = Commande::where('statut', 'confirmee')
            ->whereDoesntHave('shippingParcel')
            ->limit(3)
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No confirmed orders available for bulk testing',
                'suggestion' => 'Create some confirmed orders first'
            ]);
        }

        $ozonService = new OzonExpressService();
        $results = [];

        foreach ($orders as $order) {
            $result = $ozonService->addParcel($order);
            $results[] = [
                'order_id' => $order->id,
                'client' => $order->client->nom_complet,
                'total' => $order->total_ttc,
                'result' => $result,
            ];
        }

        $successCount = collect($results)->where('result.success', true)->count();
        $errorCount = collect($results)->where('result.success', false)->count();

        return response()->json([
            'success' => true,
            'message' => 'Bulk operations test completed',
            'ozon_enabled' => $ozonService->isEnabled(),
            'summary' => [
                'total' => count($results),
                'success' => $successCount,
                'errors' => $errorCount,
            ],
            'results' => $results,
        ]);
    }

    /**
     * Get system status for debugging
     */
    public function systemStatus(): JsonResponse
    {
        $ozonService = new OzonExpressService();

        // Count orders by status
        $orderCounts = [
            'en_attente' => Commande::where('statut', 'en_attente')->count(),
            'confirmee' => Commande::where('statut', 'confirmee')->count(),
            'with_shipping' => Commande::whereHas('shippingParcel')->count(),
            'without_shipping' => Commande::whereDoesntHave('shippingParcel')->count(),
        ];

        return response()->json([
            'success' => true,
            'ozonexpress' => [
                'enabled' => $ozonService->isEnabled(),
                'base_url' => config('services.ozonexpress.base_url'),
                'customer_id' => config('services.ozonexpress.id'),
                'api_key_set' => !empty(config('services.ozonexpress.key')),
            ],
            'orders' => $orderCounts,
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
        ]);
    }

    /**
     * Test OzonExpress API connectivity
     */
    public function testApiConnectivity(): JsonResponse
    {
        $ozonService = new OzonExpressService();

        if (!$ozonService->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'OzonExpress is disabled in configuration',
                'suggestion' => 'Run: php artisan ozonexpress:toggle --enable'
            ]);
        }

        // Test API connectivity by trying to get parcel info for a fake tracking number
        $testResult = $ozonService->getParcelInfo('TEST123456789');

        return response()->json([
            'success' => true,
            'message' => 'API connectivity test completed',
            'api_response' => $testResult,
            'interpretation' => $this->interpretApiResponse($testResult),
        ]);
    }

    /**
     * Get detailed shipping parcels information
     */
    public function getShippingParcels(): JsonResponse
    {
        // Get all parcels with analytics
        $totalParcels = \App\Models\ShippingParcel::count();
        $mockParcels = \App\Models\ShippingParcel::whereJsonContains('meta->mock_data', true)->count();
        $realParcels = $totalParcels - $mockParcels;

        // Get today's parcels
        $todayParcels = \App\Models\ShippingParcel::whereDate('created_at', today())->count();

        // Get recent parcels (prioritize real ones)
        $recentReal = \App\Models\ShippingParcel::with('commande.client')
            ->where(function($query) {
                $query->whereJsonDoesntContain('meta->mock_data', true)
                      ->orWhereNull('meta->mock_data');
            })
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        $recentMock = \App\Models\ShippingParcel::with('commande.client')
            ->whereJsonContains('meta->mock_data', true)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Combine and sort by creation date
        $allRecent = $recentReal->concat($recentMock)
            ->sortByDesc('created_at')
            ->take(20);

        $parcelsData = $allRecent->map(function ($parcel) {
            $isReal = !($parcel->meta['mock_data'] ?? false);

            return [
                'id' => $parcel->id,
                'tracking_number' => $parcel->tracking_number,
                'status' => $parcel->status,
                'provider' => $parcel->provider,
                'commande_id' => $parcel->commande_id,
                'client_name' => $parcel->commande->client->nom_complet ?? 'N/A',
                'city' => $parcel->city_name,
                'price' => $parcel->price,
                'created_at' => $parcel->created_at,
                'last_synced_at' => $parcel->last_synced_at,
                'meta' => $parcel->meta,
                'is_real' => $isReal,
                'type' => $isReal ? 'real' : 'mock',
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Shipping parcels with analytics retrieved',
            'total_parcels' => $totalParcels,
            'real_parcels' => $realParcels,
            'mock_parcels' => $mockParcels,
            'today_parcels' => $todayParcels,
            'recent_parcels' => $parcelsData->values(),
            'analytics' => [
                'total' => $totalParcels,
                'real' => $realParcels,
                'mock' => $mockParcels,
                'today' => $todayParcels,
                'real_percentage' => $totalParcels > 0 ? round(($realParcels / $totalParcels) * 100, 1) : 0,
            ]
        ]);
    }

    /**
     * Sync parcels from OzonExpress platform
     */
    public function syncParcelsFromPlatform(): JsonResponse
    {
        $ozonService = new \App\Services\OzonExpressService();

        $result = $ozonService->syncParcelsFromPlatform();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Get real parcels from OzonExpress platform
     */
    public function getRealParcelsFromPlatform(): JsonResponse
    {
        $ozonService = new \App\Services\OzonExpressService();

        $result = $ozonService->getAllParcels(50, 0);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error' => $result['error'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Real parcels from OzonExpress platform retrieved',
            'total_platform_parcels' => $result['total'],
            'platform_parcels' => $result['parcels'],
            'source' => 'ozonexpress_platform'
        ]);
    }

    /**
     * Test creating a parcel on OzonExpress platform
     */
    public function testCreateParcel(): JsonResponse
    {
        // First check if OzonExpress is enabled
        if (!config('services.ozonexpress.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'OzonExpress is disabled in configuration',
                'data' => [
                    'config_check' => [
                        'enabled' => false,
                        'base_url' => config('services.ozonexpress.base_url'),
                        'customer_id' => config('services.ozonexpress.id'),
                        'api_key_set' => !empty(config('services.ozonexpress.key')),
                    ]
                ]
            ]);
        }

        $ozonService = new \App\Services\OzonExpressService();

        $result = $ozonService->testCreateParcel();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Test tracking a specific parcel
     */
    public function testTrackParcel(Request $request): JsonResponse
    {
        $trackingNumber = $request->input('tracking_number');

        if (!$trackingNumber) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking number is required',
            ], 400);
        }

        $ozonService = new \App\Services\OzonExpressService();

        $result = $ozonService->testTrackParcel($trackingNumber);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Test basic API connectivity
     */
    public function testBasicConnectivity(): JsonResponse
    {
        $ozonService = new \App\Services\OzonExpressService();

        $result = $ozonService->testApiConnectivity();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Interpret API response for debugging
     */
    private function interpretApiResponse(array $response): array
    {
        if (!$response['success']) {
            if (str_contains($response['message'] ?? '', 'Failed to get parcel info')) {
                return [
                    'status' => 'api_error',
                    'message' => 'API call failed - check credentials and network connectivity',
                    'suggestions' => [
                        'Verify OZONEXPRESS_ID and OZONEXPRESS_KEY in .env',
                        'Check if the API endpoint is accessible',
                        'Verify network connectivity to OzonExpress servers'
                    ]
                ];
            }
        }

        return [
            'status' => 'unknown',
            'message' => 'Response received but interpretation unclear',
            'raw_response' => $response
        ];
    }
}
