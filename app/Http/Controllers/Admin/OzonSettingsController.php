<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OzonSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OzonSettingsController extends Controller
{
    protected OzonSettingsService $ozonSettingsService;

    public function __construct(OzonSettingsService $ozonSettingsService)
    {
        $this->ozonSettingsService = $ozonSettingsService;
    }

    /**
     * Get OzonExpress settings (masked)
     */
    public function show(): JsonResponse
    {
        try {
            $settings = $this->ozonSettingsService->getSettingsForDisplay();

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get OzonExpress settings for editing (unmasked)
     */
    public function edit(): JsonResponse
    {
        try {
            $settings = $this->ozonSettingsService->getSettingsForEdit();

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings for editing',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update OzonExpress settings
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $this->ozonSettingsService->updateSettings($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $this->ozonSettingsService->getSettingsForDisplay(),
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
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test OzonExpress connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->ozonSettingsService->testConnection();
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test connection',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
