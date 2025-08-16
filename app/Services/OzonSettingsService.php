<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\ShippingCity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OzonSettingsService
{
    /**
     * Get OzonExpress settings
     */
    public function getSettings(): array
    {
        return [
            'customer_id' => AppSetting::get('ozonexpress.customer_id'),
            'api_key' => AppSetting::get('ozonexpress.api_key'),
            'base_url' => AppSetting::get('ozonexpress.base_url', 'https://api.ozonexpress.ma'),
            'enabled' => AppSetting::get('ozonexpress.enabled', true),
        ];
    }

    /**
     * Update OzonExpress settings
     */
    public function updateSettings(array $data): array
    {
        $this->validateSettings($data);

        AppSetting::set(
            'ozonexpress.customer_id',
            $data['customer_id'],
            'string',
            false,
            'OzonExpress Customer ID'
        );

        AppSetting::set(
            'ozonexpress.api_key',
            $data['api_key'],
            'string',
            true, // Encrypt API key
            'OzonExpress API Key'
        );

        if (isset($data['base_url'])) {
            AppSetting::set(
                'ozonexpress.base_url',
                $data['base_url'],
                'string',
                false,
                'OzonExpress API Base URL'
            );
        }

        // Clear config cache to reload settings
        $this->clearConfigCache();

        return $this->getSettings();
    }

    /**
     * Validate settings data
     */
    protected function validateSettings(array $data): void
    {
        $validator = Validator::make($data, [
            'customer_id' => 'required|string|max:32',
            'api_key' => 'required|string|max:128',
            'base_url' => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Get masked API key for display
     */
    public function getMaskedApiKey(): ?string
    {
        $apiKey = AppSetting::get('ozonexpress.api_key');
        
        if (!$apiKey) {
            return null;
        }

        $length = strlen($apiKey);
        
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($apiKey, 0, 4) . str_repeat('*', $length - 8) . substr($apiKey, -4);
    }

    /**
     * Get settings for display (with masked API key)
     */
    public function getSettingsForDisplay(): array
    {
        return [
            'customer_id' => AppSetting::get('ozonexpress.customer_id'),
            'api_key' => $this->getMaskedApiKey(),
            'base_url' => AppSetting::get('ozonexpress.base_url', 'https://api.ozonexpress.ma'),
        ];
    }

    /**
     * Get settings for editing (with real API key)
     */
    public function getSettingsForEdit(): array
    {
        return [
            'customer_id' => AppSetting::get('ozonexpress.customer_id'),
            'api_key' => AppSetting::get('ozonexpress.api_key'),
        ];
    }

    /**
     * Test OzonExpress connectivity
     */
    public function testConnection(): array
    {
        $settings = $this->getSettings();

        if (!$settings['customer_id'] || !$settings['api_key']) {
            return [
                'success' => false,
                'message' => 'OzonExpress credentials not configured',
            ];
        }

        // Basic validation of credentials format
        if (strlen($settings['customer_id']) < 3) {
            return [
                'success' => false,
                'message' => 'Customer ID appears to be invalid (too short)',
            ];
        }

        if (strlen($settings['api_key']) < 10) {
            return [
                'success' => false,
                'message' => 'API Key appears to be invalid (too short)',
            ];
        }

        // Test actual API connectivity by calling the /cities endpoint
        try {
            $response = $this->callOzonExpressApi('/cities', 'GET');

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Connection successful! API credentials are valid.',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Connection failed: ' . ($response['message'] ?? 'Unknown error'),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Make an API call to OzonExpress
     */
    protected function callOzonExpressApi(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $settings = $this->getSettings();

        if (!$settings['customer_id'] || !$settings['api_key']) {
            throw new \Exception('OzonExpress credentials not configured');
        }

        // Get base URL from settings
        $baseUrl = $settings['base_url'] ?? 'https://api.ozonexpress.ma';

        try {
            // OzonExpress uses path-based authentication: /{customer_id}/{api_key}/endpoint
            $authenticatedUrl = rtrim($baseUrl, '/') . '/' . $settings['customer_id'] . '/' . $settings['api_key'] . $endpoint;

            $http = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]);

            if ($method === 'GET') {
                $response = $http->get($authenticatedUrl);
            } elseif ($method === 'POST') {
                $response = $http->post($authenticatedUrl, $data);
            } else {
                throw new \Exception('Unsupported HTTP method: ' . $method);
            }

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'status_code' => $response->status(),
                ];
            } else {
                $statusCode = $response->status();
                if ($statusCode === 401) {
                    throw new \Exception('Authentication failed. Please check your API credentials.');
                } elseif ($statusCode === 403) {
                    throw new \Exception('Access forbidden. Please check your API permissions.');
                } else {
                    throw new \Exception('API request failed with status ' . $statusCode . ': ' . $response->body());
                }
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \Exception('Unable to connect to OzonExpress API. Please check your internet connection.');
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw new \Exception('OzonExpress API server error. Please try again later.');
        } catch (\Exception $e) {
            throw new \Exception('API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear configuration cache
     */
    protected function clearConfigCache(): void
    {
        Cache::forget('app_setting_ozonexpress.customer_id');
        Cache::forget('app_setting_ozonexpress.api_key');

        // Clear the service provider cache
        \App\Providers\AppSettingsServiceProvider::clearCache();
    }

    /**
     * Get cities with filtering and pagination
     */
    public function getCities(array $filters = []): array
    {
        $query = ShippingCity::ozonExpressCities();

        // Apply soft delete filter
        $includeDeleted = $filters['include_deleted'] ?? 'active';
        if ($includeDeleted === 'deleted') {
            $query->onlyTrashed();
        } elseif ($includeDeleted === 'all') {
            $query->withTrashed();
        }
        // Default is 'active' which shows only non-deleted records

        // Apply search filter
        if (!empty($filters['q'])) {
            $query->search($filters['q']);
        }

        // Apply active filter
        if (isset($filters['active']) && $filters['active'] !== '') {
            $query->active((bool) $filters['active']);
        }

        // Apply pagination
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        return $query->orderBy('name')
                    ->paginate($perPage, ['*'], 'page', $page)
                    ->toArray();
    }

    /**
     * Create a new city
     */
    public function createCity(array $data): ShippingCity
    {
        $this->validateCityData($data);

        $data['provider'] = 'ozonexpress';
        
        return ShippingCity::create($data);
    }

    /**
     * Update an existing city
     */
    public function updateCity(string $id, array $data): ShippingCity
    {
        $this->validateCityData($data, $id);

        $city = ShippingCity::findOrFail($id);
        $city->update($data);

        return $city->fresh();
    }

    /**
     * Soft delete a city
     */
    public function deleteCity(string $id): bool
    {
        $city = ShippingCity::findOrFail($id);

        return $city->delete();
    }

    /**
     * Restore a soft deleted city
     */
    public function restoreCity(string $id): bool
    {
        $city = ShippingCity::withTrashed()->findOrFail($id);

        return $city->restore();
    }

    /**
     * Permanently delete a city
     */
    public function forceDeleteCity(string $id): bool
    {
        $city = ShippingCity::withTrashed()->findOrFail($id);

        return $city->forceDelete();
    }

    /**
     * Validate city data
     */
    protected function validateCityData(array $data, ?string $excludeId = null): void
    {
        $rules = [
            'city_id' => 'required|string|max:32',
            'name' => 'required|string|max:120',
            'ref' => 'nullable|string|max:10',
            'active' => 'boolean',
            'prices' => 'nullable|array',
            'prices.delivered' => 'nullable|numeric|min:0',
            'prices.returned' => 'nullable|numeric|min:0',
            'prices.refused' => 'nullable|numeric|min:0',
            'meta' => 'nullable|array',
        ];

        // Add unique validation for city_id within provider
        if ($excludeId) {
            $rules['city_id'] .= '|unique:shipping_cities,city_id,' . $excludeId . ',id,provider,ozonexpress';
        } else {
            $rules['city_id'] .= '|unique:shipping_cities,city_id,NULL,id,provider,ozonexpress';
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Import cities from file
     */
    public function importCitiesFromFile(string $filePath): array
    {
        // This would typically call the import command
        // For now, return a placeholder response
        return [
            'success' => true,
            'message' => 'Import functionality will be implemented for: ' . basename($filePath),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];
    }

    /**
     * Get city statistics
     */
    public function getCityStats(): array
    {
        $total = ShippingCity::ozonExpressCities()->count();
        $active = ShippingCity::ozonExpressCities()->active()->count();
        $inactive = $total - $active;

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }
}
