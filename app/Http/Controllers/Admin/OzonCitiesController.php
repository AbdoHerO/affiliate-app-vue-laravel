<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OzonSettingsService;
use App\Models\ShippingCity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OzonCitiesController extends Controller
{
    protected OzonSettingsService $ozonSettingsService;

    public function __construct(OzonSettingsService $ozonSettingsService)
    {
        $this->ozonSettingsService = $ozonSettingsService;
    }

    /**
     * Get paginated list of cities with filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'q' => $request->get('q'),
                'active' => $request->get('active'),
                'include_deleted' => $request->get('include_deleted', 'active'),
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 15),
            ];

            $cities = $this->ozonSettingsService->getCities($filters);
            $stats = $this->ozonSettingsService->getCityStats();
            
            return response()->json([
                'success' => true,
                'data' => $cities,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new city
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $city = $this->ozonSettingsService->createCity($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'City created successfully',
                'data' => $city,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create city',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific city
     */
    public function show(string $id): JsonResponse
    {
        try {
            $city = ShippingCity::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $city,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'City not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update an existing city
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $city = $this->ozonSettingsService->updateCity($id, $request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'City updated successfully',
                'data' => $city,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update city',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Soft delete a city
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $deleted = $this->ozonSettingsService->deleteCity($id);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'City deleted successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete city',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete city',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft deleted city
     */
    public function restore(string $id): JsonResponse
    {
        try {
            $restored = $this->ozonSettingsService->restoreCity($id);

            if ($restored) {
                return response()->json([
                    'success' => true,
                    'message' => 'City restored successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to restore city',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore city',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Permanently delete a city
     */
    public function forceDelete(string $id): JsonResponse
    {
        try {
            $deleted = $this->ozonSettingsService->forceDeleteCity($id);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'City permanently deleted',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to permanently delete city',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete city',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import cities from uploaded file
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:json,csv|max:2048',
            ]);

            $file = $request->file('file');
            $path = $file->store('temp', 'local');
            $fullPath = storage_path("app/{$path}");

            $result = $this->ozonSettingsService->importCitiesFromFile($fullPath);

            // Clean up temporary file
            unlink($fullPath);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ], $result['success'] ? 200 : 400);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import cities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get city statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->ozonSettingsService->getCityStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
