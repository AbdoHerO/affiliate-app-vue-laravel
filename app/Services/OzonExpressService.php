<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\ShippingParcel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OzonExpressService
{
    private string $baseUrl;
    private string $customerId;
    private string $apiKey;
    private bool $enabled;

    public function __construct()
    {
        $this->baseUrl = config('services.ozonexpress.base_url', 'https://api.ozonexpress.ma');
        $this->customerId = config('services.ozonexpress.id');
        $this->apiKey = config('services.ozonexpress.key');
        $this->enabled = config('services.ozonexpress.enabled', true);
    }

    /**
     * Check if OzonExpress is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Add a parcel to OzonExpress from a commande (idempotent)
     */
    public function addParcel(Commande $commande, ?string $trackingNumber = null): array
    {
        // Check if parcel already exists (idempotency)
        if ($commande->shippingParcel) {
            return [
                'success' => true,
                'message' => 'Parcel already exists',
                'data' => $commande->shippingParcel,
                'exists' => true,
            ];
        }

        // If OzonExpress is disabled, create mock parcel
        if (!$this->enabled) {
            return $this->createMockParcel($commande);
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
                'tracking-number' => $trackingNumber ?? '',
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

            // Log the API call for debugging
            Log::info('OzonExpress API Call', [
                'commande_id' => $commande->id,
                'url' => "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel",
                'form_data' => $formData,
            ]);

            // Make API call to OzonExpress
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel",
                $formData
            );

            // Log the response for debugging
            Log::info('OzonExpress API Response', [
                'commande_id' => $commande->id,
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                Log::error('OzonExpress API Error', [
                    'commande_id' => $commande->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to create parcel at OzonExpress',
                    'error' => $response->body(),
                    'debug_info' => [
                        'url' => "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel",
                        'status_code' => $response->status(),
                        'response_headers' => $response->headers(),
                    ],
                ];
            }

            $responseData = $response->json();

            // Parse response according to API documentation
            $trackingNumber = $responseData[0] ?? null;
            $receiver = $responseData[1] ?? $commande->client->nom_complet;
            $phone = $responseData[2] ?? $commande->client->telephone;
            $cityId = $responseData[3] ?? null;
            $cityName = $responseData[4] ?? $commande->adresse->ville;
            $address = $responseData[5] ?? $commande->adresse->adresse;
            $price = $responseData[6] ?? $commande->total_ttc;
            $note = $responseData[7] ?? $commande->notes;
            $deliveredPrice = $responseData[8] ?? null;
            $returnedPrice = $responseData[9] ?? null;
            $refusedPrice = $responseData[10] ?? null;

            // Create shipping parcel record
            $parcel = ShippingParcel::create([
                'commande_id' => $commande->id,
                'provider' => 'ozonexpress',
                'tracking_number' => $trackingNumber,
                'status' => 'pending',
                'city_id' => $cityId,
                'city_name' => $cityName,
                'receiver' => $receiver,
                'phone' => $phone,
                'address' => $address,
                'price' => $price,
                'note' => $note,
                'delivered_price' => $deliveredPrice,
                'returned_price' => $returnedPrice,
                'refused_price' => $refusedPrice,
                'last_synced_at' => now(),
                'meta' => [
                    'api_response' => $responseData,
                    'form_data' => $formData,
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Parcel created successfully',
                'data' => $parcel,
            ];

        } catch (\Exception $e) {
            Log::error('OzonExpress Service Error', [
                'commande_id' => $commande->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create parcel: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a mock parcel for testing when OzonExpress is disabled
     */
    private function createMockParcel(Commande $commande): array
    {
        $mockTrackingNumber = 'MOCK' . now()->format('YmdHis') . rand(1000, 9999);

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

        return [
            'success' => true,
            'message' => 'Mock parcel created successfully (OzonExpress disabled)',
            'data' => $parcel,
            'mock' => true,
        ];
    }

    /**
     * Get parcel information by tracking number
     */
    public function getParcelInfo(string $trackingNumber): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => 'OzonExpress is disabled',
            ];
        }

        try {
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/parcel-info",
                ['tracking-number' => $trackingNumber]
            );

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to get parcel info',
                    'error' => $response->body(),
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get parcel info: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get tracking information for a parcel
     */
    public function getTracking(string $trackingNumber): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => 'OzonExpress is disabled',
            ];
        }

        try {
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/tracking",
                ['tracking-number' => $trackingNumber]
            );

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to get tracking info',
                    'error' => $response->body(),
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get tracking info: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }
}
