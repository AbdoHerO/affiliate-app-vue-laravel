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
}
