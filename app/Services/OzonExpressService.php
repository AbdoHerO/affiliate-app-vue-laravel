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

    /**
     * Get all parcels from OzonExpress platform
     */
    public function getAllParcels(int $limit = 50, int $offset = 0): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => 'OzonExpress is disabled',
            ];
        }

        try {
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/parcels",
                [
                    'limit' => $limit,
                    'offset' => $offset
                ]
            );

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to get parcels list',
                    'error' => $response->body(),
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'data' => $data,
                'parcels' => $data['parcels'] ?? [],
                'total' => $data['total'] ?? 0,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get parcels list: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync local database with OzonExpress platform data
     */
    public function syncParcelsFromPlatform(): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => 'OzonExpress is disabled',
            ];
        }

        try {
            $result = $this->getAllParcels(100, 0); // Get up to 100 recent parcels

            if (!$result['success']) {
                return $result;
            }

            $platformParcels = $result['parcels'];
            $syncedCount = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($platformParcels as $platformParcel) {
                try {
                    $trackingNumber = $platformParcel['tracking_number'] ?? null;

                    if (!$trackingNumber) {
                        continue;
                    }

                    // Find existing parcel in local database
                    $localParcel = \App\Models\ShippingParcel::where('tracking_number', $trackingNumber)->first();

                    if ($localParcel) {
                        // Update existing parcel with platform data
                        $localParcel->update([
                            'status' => $platformParcel['status'] ?? $localParcel->status,
                            'city_name' => $platformParcel['city'] ?? $localParcel->city_name,
                            'price' => $platformParcel['price'] ?? $localParcel->price,
                            'last_synced_at' => now(),
                            'meta' => array_merge($localParcel->meta ?? [], [
                                'platform_data' => $platformParcel,
                                'synced_from_platform' => true,
                                'last_platform_sync' => now()->toISOString()
                            ])
                        ]);
                        $updatedCount++;
                    } else {
                        // Create new parcel from platform data
                        \App\Models\ShippingParcel::create([
                            'tracking_number' => $trackingNumber,
                            'status' => $platformParcel['status'] ?? 'unknown',
                            'provider' => 'OzonExpress',
                            'city_name' => $platformParcel['city'] ?? null,
                            'price' => $platformParcel['price'] ?? null,
                            'last_synced_at' => now(),
                            'meta' => [
                                'platform_data' => $platformParcel,
                                'synced_from_platform' => true,
                                'created_from_platform' => true,
                                'last_platform_sync' => now()->toISOString()
                            ]
                        ]);
                        $syncedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error syncing parcel {$trackingNumber}: " . $e->getMessage();
                }
            }

            return [
                'success' => true,
                'message' => "Synchronization completed",
                'synced_new' => $syncedCount,
                'updated_existing' => $updatedCount,
                'total_platform_parcels' => count($platformParcels),
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to sync parcels: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get valid cities from OzonExpress API
     */
    public function getValidCities(): array
    {
        try {
            $response = Http::timeout(10)->get('https://api.ozonexpress.ma/cities');

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::warning('Failed to get cities from OzonExpress', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Exception getting cities from OzonExpress', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Test basic API connectivity
     */
    public function testApiConnectivity(): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => 'OzonExpress is disabled',
            ];
        }

        try {
            // Test with a simple API call to check connectivity
            $response = Http::timeout(10)->get('https://api.ozonexpress.ma/cities');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'OzonExpress API is reachable',
                    'cities_count' => count($response->json()),
                    'config' => [
                        'base_url' => $this->baseUrl,
                        'customer_id' => $this->customerId,
                        'api_key_set' => !empty($this->apiKey),
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'OzonExpress API is not reachable',
                    'status_code' => $response->status(),
                    'error' => $response->body(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect to OzonExpress API: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test creating a parcel on OzonExpress platform
     */
    public function testCreateParcel(): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => 'OzonExpress is disabled',
            ];
        }

        // Create test parcel data using the same format as real parcels
        $testProducts = [
            [
                'ref' => 'Test Product Debug',
                'qnty' => 1,
            ]
        ];

        // Use Casablanca with different formats to test what works
        // Based on the official OzonExpress API cities data
        $cityId = '97'; // Official Casablanca CITY_ID from API
        $cityRef = 'CSA'; // Official Casablanca REF from API
        $cityName = 'Casablanca'; // Official CITY_NAME from API

        Log::info('Using valid city for test', [
            'selected_city_id' => $cityId,
            'selected_city_ref' => $cityRef,
            'selected_city_name' => $cityName,
            'note' => 'Testing with official REF format from OzonExpress API'
        ]);

        $testParcelData = [
            'tracking-number' => '', // Let OzonExpress generate
            'parcel-receiver' => 'Test Client Debug',
            'parcel-phone' => '0612345678',
            'parcel-city' => $cityId, // Try using CITY_ID as string (97 for Casablanca)
            'parcel-address' => 'Test Address, ' . $cityName . ', Morocco',
            'parcel-note' => 'Test parcel from debug interface - RAMASSAGE',
            'parcel-price' => 150.00,
            'parcel-nature' => 'Test Product',
            'parcel-stock' => '0', // 0 = ramassage (pickup), 1 = stock
            'products' => json_encode($testProducts),
        ];

        try {
            // Log the API call for debugging
            Log::info('OzonExpress Test API Call', [
                'url' => "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel",
                'data' => $testParcelData,
            ]);

            // Use the same endpoint as real parcels
            $response = Http::asForm()->post(
                "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel",
                $testParcelData
            );

            // Log the response for debugging
            Log::info('OzonExpress Test API Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to create test parcel on OzonExpress',
                    'error' => $response->body(),
                    'status_code' => $response->status(),
                    'test_data' => $testParcelData,
                    'api_url' => "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel",
                ];
            }

            // Try to parse as JSON first, then as array
            $responseBody = $response->body();
            $responseData = null;

            // Log the raw response for debugging
            Log::info('OzonExpress Raw Response', [
                'status' => $response->status(),
                'body' => $responseBody,
                'headers' => $response->headers()
            ]);

            try {
                $responseData = $response->json();
            } catch (\Exception $e) {
                // If JSON parsing fails, try to parse as array
                Log::warning('Failed to parse JSON response, trying array parsing', [
                    'body' => $responseBody,
                    'error' => $e->getMessage()
                ]);

                // Sometimes the response might be a simple array format
                $responseData = json_decode($responseBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $responseData = [$responseBody]; // Fallback to raw response
                }
            }

            // Parse response according to OzonExpress API format
            // The API returns a JSON object with CHECK_API and ADD-PARCEL sections
            if (!is_array($responseData) || !isset($responseData['ADD-PARCEL'])) {
                Log::error('Invalid OzonExpress API response format', [
                    'response_data' => $responseData,
                    'response_body' => $responseBody,
                    'expected' => 'JSON object with ADD-PARCEL section'
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid response format from OzonExpress API',
                    'error' => 'Expected JSON object with ADD-PARCEL section',
                    'raw_response' => $responseData,
                    'raw_body' => $responseBody,
                    'test_data' => $testParcelData,
                ];
            }

            // Check if the ADD-PARCEL operation was successful
            $addParcelResult = $responseData['ADD-PARCEL'];
            if ($addParcelResult['RESULT'] !== 'SUCCESS') {
                Log::error('OzonExpress ADD-PARCEL failed', [
                    'add_parcel_result' => $addParcelResult,
                    'full_response' => $responseData
                ]);

                return [
                    'success' => false,
                    'message' => 'OzonExpress API Error: ' . ($addParcelResult['MESSAGE'] ?? 'Unknown error'),
                    'error' => $addParcelResult['MESSAGE'] ?? 'Unknown error',
                    'api_result' => $addParcelResult,
                    'raw_response' => $responseData,
                    'test_data' => $testParcelData,
                ];
            }

            // If successful, the tracking number should be in the response
            // The response structure is: ADD-PARCEL.NEW-PARCEL.TRACKING-NUMBER
            $trackingNumber = $addParcelResult['NEW-PARCEL']['TRACKING-NUMBER'] ??
                             $addParcelResult['TRACKING_NUMBER'] ??
                             $addParcelResult['tracking_number'] ?? null;

            // Check if we got a valid tracking number
            if (empty($trackingNumber) || $trackingNumber === 'TEST_' . time()) {
                Log::error('No valid tracking number received from OzonExpress', [
                    'response_data' => $responseData,
                    'tracking_number' => $trackingNumber,
                    'response_type' => gettype($responseData),
                    'response_count' => is_array($responseData) ? count($responseData) : 'not_array',
                    'raw_body' => $response->body(),
                    'status_code' => $response->status()
                ]);

                return [
                    'success' => false,
                    'message' => 'No valid tracking number received from OzonExpress API',
                    'error' => 'Empty or invalid tracking number',
                    'raw_response' => $responseData,
                    'raw_body' => $response->body(),
                    'status_code' => $response->status(),
                    'response_type' => gettype($responseData),
                    'test_data' => $testParcelData,
                    'debug_info' => [
                        'api_url' => "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel",
                        'expected_format' => 'Array with tracking number as first element',
                        'actual_response' => $responseData
                    ]
                ];
            }

            // Extract data from the NEW-PARCEL object in the response
            $newParcel = $addParcelResult['NEW-PARCEL'] ?? [];
            $receiver = $newParcel['RECEIVER'] ?? $testParcelData['parcel-receiver'];
            $phone = $newParcel['PHONE'] ?? $testParcelData['parcel-phone'];
            $cityId = $newParcel['CITY_ID'] ?? null;
            $cityName = $newParcel['CITY_NAME'] ?? $testParcelData['parcel-city'];
            $address = $newParcel['ADDRESS'] ?? $testParcelData['parcel-address'];
            $price = $newParcel['PRICE'] ?? $testParcelData['parcel-price'];
            $note = $newParcel['NOTE'] ?? $testParcelData['parcel-note'];

            // Save test parcel to local database
            $parcel = \App\Models\ShippingParcel::create([
                'tracking_number' => $trackingNumber,
                'status' => 'pending',
                'provider' => 'OzonExpress',
                'commande_id' => null, // Test parcel doesn't have a real order
                'receiver' => $receiver,
                'phone' => $phone,
                'city_name' => $cityName,
                'address' => $address,
                'price' => $price,
                'note' => $note,
                'last_synced_at' => now(),
                'meta' => [
                    'test_parcel' => true,
                    'debug_created' => true,
                    'ramassage' => true, // This is a pickup parcel
                    'platform_response' => $responseData,
                    'test_data' => $testParcelData,
                    'created_at_debug' => now()->toISOString(),
                    'city_id' => $cityId,
                ]
            ]);

            return [
                'success' => true,
                'message' => 'Test parcel created successfully on OzonExpress platform (RAMASSAGE)',
                'tracking_number' => $trackingNumber,
                'platform_response' => $responseData,
                'local_parcel_id' => $parcel->id,
                'test_data' => [
                    'client_name' => $receiver,
                    'phone' => $phone,
                    'city' => $cityName,
                    'address' => $address,
                    'price' => $price,
                    'note' => $note,
                    'type' => 'RAMASSAGE (Pickup)',
                ],
                'api_url' => "{$this->baseUrl}/customers/{$this->customerId}/{$this->apiKey}/add-parcel",
                'parcel_type' => 'ramassage',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create test parcel: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'test_data' => $testParcelData,
            ];
        }
    }

    /**
     * Test tracking a specific parcel
     */
    public function testTrackParcel(string $trackingNumber): array
    {
        if (!$this->enabled) {
            return [
                'success' => false,
                'message' => 'OzonExpress is disabled',
            ];
        }

        try {
            // Get parcel info from platform
            $parcelInfo = $this->getParcelInfo($trackingNumber);
            $trackingInfo = $this->getTracking($trackingNumber);

            // Get local parcel data
            $localParcel = \App\Models\ShippingParcel::where('tracking_number', $trackingNumber)->first();

            $result = [
                'success' => true,
                'message' => 'Parcel tracking information retrieved',
                'tracking_number' => $trackingNumber,
                'platform_parcel_info' => $parcelInfo,
                'platform_tracking_info' => $trackingInfo,
                'local_parcel' => $localParcel ? [
                    'id' => $localParcel->id,
                    'status' => $localParcel->status,
                    'city_name' => $localParcel->city_name,
                    'price' => $localParcel->price,
                    'created_at' => $localParcel->created_at,
                    'last_synced_at' => $localParcel->last_synced_at,
                    'meta' => $localParcel->meta,
                ] : null,
                'comparison' => [
                    'exists_locally' => $localParcel !== null,
                    'exists_on_platform' => $parcelInfo['success'] && $trackingInfo['success'],
                    'status_match' => $localParcel && $parcelInfo['success'] ?
                        ($localParcel->status === ($parcelInfo['data']['status'] ?? 'unknown')) : null,
                ]
            ];

            // Update local parcel if it exists and platform data is available
            if ($localParcel && $parcelInfo['success']) {
                $platformData = $parcelInfo['data'];
                $localParcel->update([
                    'status' => $platformData['status'] ?? $localParcel->status,
                    'last_synced_at' => now(),
                    'meta' => array_merge($localParcel->meta ?? [], [
                        'last_tracking_check' => now()->toISOString(),
                        'platform_status' => $platformData['status'] ?? null,
                        'tracking_history' => $trackingInfo['data'] ?? null,
                    ])
                ]);

                $result['local_parcel_updated'] = true;
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to track parcel: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'tracking_number' => $trackingNumber,
            ];
        }
    }
}
