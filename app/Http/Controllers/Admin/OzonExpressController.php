<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\ShippingParcel;
use App\Models\ShippingCity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OzonExpressController extends Controller
{
    private string $baseUrl;
    private string $customerId;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.ozonexpress.base_url', 'https://api.ozonexpress.ma');
        $this->customerId = config('services.ozonexpress.id');
        $this->apiKey = config('services.ozonexpress.key');
    }

    /**
     * Check if OzonExpress is enabled
     */
    private function isOzonExpressEnabled(): bool
    {
        return config('services.ozonexpress.enabled', true);
    }

    /**
     * Create a mock parcel for testing when OzonExpress is disabled
     */
    private function createMockParcel(Commande $commande, ?string $trackingNumber = null): JsonResponse
    {
        $mockTrackingNumber = $trackingNumber ?: 'MOCK' . strtoupper(\Illuminate\Support\Str::random(8)) . rand(1000, 9999);

        $parcel = ShippingParcel::create([
            'commande_id' => $commande->id,
            'provider' => 'ozonexpress',
            'tracking_number' => $mockTrackingNumber,
            'status' => 'pending',
            'city_name' => $commande->adresse->ville,
            'receiver' => $commande->client->nom_complet,
            'phone' => $commande->client->telephone,
            'address' => $commande->adresse->adresse,
            'price' => 35.00, // Mock delivery price
            'note' => 'Mock parcel created for testing (OzonExpress disabled)',
            'last_synced_at' => now(),
            'meta' => [
                'mock_data' => true,
                'created_at' => now()->toISOString(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mock parcel created successfully (OzonExpress disabled)',
            'data' => $parcel,
        ]);
    }

    /**
     * Create a parcel at OzonExpress from a commande.
     */
    public function addParcel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commande_id' => 'required|exists:commandes,id',
            'tracking_number' => 'sometimes|string|nullable',
        ]);

        $commande = Commande::with([
            'client',
            'adresse',
            'articles.produit',
            'articles.variante',
            'shippingParcel'
        ])->findOrFail($validated['commande_id']);

        // Check if parcel already exists (idempotency)
        if ($commande->shippingParcel) {
            return response()->json([
                'success' => true,
                'message' => 'Parcel already exists',
                'data' => $commande->shippingParcel,
            ]);
        }

        // If OzonExpress is disabled, create mock parcel for testing
        if (!$this->isOzonExpressEnabled()) {
            return $this->createMockParcel($commande, $validated['tracking_number'] ?? null);
        }

        try {
            // Prepare products array
            $products = $commande->articles->map(function ($article) {
                return [
                    'ref' => $article->produit->titre,
                    'qnty' => $article->quantite,
                ];
            })->toArray();

            // Prepare form data for OzonExpress API
            $formData = [
                'tracking-number' => $validated['tracking_number'] ?? '',
                'parcel-receiver' => $commande->client->nom_complet,
                'parcel-phone' => $commande->client->telephone,
                'parcel-city' => $commande->adresse->ville,
                'parcel-address' => $commande->adresse->adresse,
                'parcel-note' => $commande->notes ?? '',
                'parcel-price' => $commande->total_ttc,
                'parcel-nature' => 'Produits divers',
                'parcel-stock' => '1', // 1 = stock, 0 = pickup
                'products' => json_encode($products),
            ];

            // Make API call to OzonExpress
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel",
                $formData
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create parcel at OzonExpress',
                    'error' => $response->body(),
                ], 500);
            }

            $responseData = $response->json();

            // Parse response (array format as per API docs)
            $parcelData = [
                'tracking_number' => $responseData[0] ?? null,
                'receiver' => $responseData[1] ?? null,
                'phone' => $responseData[2] ?? null,
                'city_id' => $responseData[3] ?? null,
                'city_name' => $responseData[4] ?? null,
                'address' => $responseData[5] ?? null,
                'price' => $responseData[6] ?? null,
                'note' => $responseData[7] ?? null,
                'delivered_price' => $responseData[8] ?? null,
                'returned_price' => $responseData[9] ?? null,
                'refused_price' => $responseData[10] ?? null,
            ];

            // Create shipping parcel record
            $shippingParcel = ShippingParcel::create([
                'commande_id' => $commande->id,
                'provider' => 'ozonexpress',
                'tracking_number' => $parcelData['tracking_number'],
                'status' => 'created',
                'city_id' => $parcelData['city_id'],
                'city_name' => $parcelData['city_name'],
                'receiver' => $parcelData['receiver'],
                'phone' => $parcelData['phone'],
                'address' => $parcelData['address'],
                'price' => $parcelData['price'],
                'note' => $parcelData['note'],
                'delivered_price' => $parcelData['delivered_price'],
                'returned_price' => $parcelData['returned_price'],
                'refused_price' => $parcelData['refused_price'],
                'last_synced_at' => now(),
                'meta' => $responseData,
            ]);

            // Update order status to shipped
            $commande->update(['statut' => 'expediee']);

            return response()->json([
                'success' => true,
                'message' => 'Parcel created successfully',
                'data' => $shippingParcel,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create parcel',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get parcel information by tracking number.
     */
    public function parcelInfo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string',
        ]);

        try {
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/parcel-info",
                ['tracking-number' => $validated['tracking_number']]
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get parcel info',
                    'error' => $response->body(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get parcel info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tracking information by tracking number.
     */
    public function tracking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string',
        ]);

        try {
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/tracking",
                ['tracking-number' => $validated['tracking_number']]
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get tracking info',
                    'error' => $response->body(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get tracking info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a delivery note.
     */
    public function createDeliveryNote(): JsonResponse
    {
        try {
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-delivery-note"
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create delivery note',
                    'error' => $response->body(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => ['ref' => $response->body()], // API returns just the ref
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create delivery note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add parcels to delivery note.
     */
    public function addParcelsToDeliveryNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ref' => 'required|string',
            'codes' => 'required|array',
            'codes.*' => 'required|string',
        ]);

        try {
            $formData = ['Ref' => $validated['ref']];

            // Add codes as array format expected by API
            foreach ($validated['codes'] as $index => $code) {
                $formData["Codes[{$index}]"] = $code;
            }

            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel-to-delivery-note",
                $formData
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to add parcels to delivery note',
                    'error' => $response->body(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Parcels added to delivery note successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add parcels to delivery note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save delivery note.
     */
    public function saveDeliveryNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ref' => 'required|string',
        ]);

        try {
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/save-delivery-note",
                ['Ref' => $validated['ref']]
            );

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save delivery note',
                    'error' => $response->body(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Delivery note saved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save delivery note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
