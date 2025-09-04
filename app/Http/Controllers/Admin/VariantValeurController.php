<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VariantAttribut;
use App\Models\VariantValeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VariantValeurController extends Controller
{
    /**
     * Display values for a specific attribute
     */
    public function index(Request $request, VariantAttribut $variantAttribut): JsonResponse
    {
        try {
            $query = $variantAttribut->valeurs();

            // Search by code or libelle
            if ($request->filled('q')) {
                $search = $request->get('q');
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'LIKE', "%{$search}%")
                      ->orWhere('libelle', 'LIKE', "%{$search}%");
                });
            }

            // Filter by actif status
            if ($request->has('actif') && $request->get('actif') !== '') {
                $query->where('actif', $request->boolean('actif'));
            }

            // Always order by ordre, then libelle
            $query->orderBy('ordre')->orderBy('libelle');

            $valeurs = $query->get();

            return response()->json([
                'success' => true,
                'data' => $valeurs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching variant values'
            ], 500);
        }
    }

    /**
     * Store a newly created value for an attribute
     */
    public function store(Request $request, VariantAttribut $variantAttribut): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => [
                    'required',
                    'string',
                    'alpha_dash',
                    'max:50',
                    function ($attribute, $value, $fail) use ($variantAttribut) {
                        if ($variantAttribut->valeurs()->where('code', $value)->exists()) {
                            $fail('The code has already been taken for this attribute.');
                        }
                    }
                ],
                'libelle' => 'required|string|max:100',
                'actif' => 'boolean',
                'ordre' => 'integer|min:0',
                'hex_color' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value && !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
                            $fail('The hex color must be a valid hex color code (e.g., #FF0000 or #fff).');
                        }
                    }
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $data['attribut_id'] = $variantAttribut->id;

            // Set ordre to max + 1 if not provided
            if (!isset($data['ordre'])) {
                $maxOrdre = $variantAttribut->valeurs()->max('ordre') ?? 0;
                $data['ordre'] = $maxOrdre + 1;
            }

            // Convert empty hex_color to null
            if (isset($data['hex_color']) && empty(trim($data['hex_color']))) {
                $data['hex_color'] = null;
            }

            $valeur = VariantValeur::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Variant value created successfully',
                'data' => $valeur
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating variant value: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all(),
                'attribut_id' => $variantAttribut->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating variant value',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update the specified value
     */
    public function update(Request $request, VariantAttribut $variantAttribut, VariantValeur $variantValeur): JsonResponse
    {
        try {
            // Ensure the value belongs to the attribute
            if ($variantValeur->attribut_id !== $variantAttribut->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Value does not belong to this attribute'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'code' => [
                    'required',
                    'string',
                    'alpha_dash',
                    'max:50',
                    function ($attribute, $value, $fail) use ($variantAttribut, $variantValeur) {
                        if ($variantAttribut->valeurs()->where('code', $value)->where('id', '!=', $variantValeur->id)->exists()) {
                            $fail('The code has already been taken for this attribute.');
                        }
                    }
                ],
                'libelle' => 'required|string|max:100',
                'actif' => 'boolean',
                'ordre' => 'integer|min:0',
                'hex_color' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value && !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $value)) {
                            $fail('The hex color must be a valid hex color code (e.g., #FF0000 or #fff).');
                        }
                    }
                ]
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Convert empty hex_color to null
            if (isset($data['hex_color']) && empty(trim($data['hex_color']))) {
                $data['hex_color'] = null;
            }

            $variantValeur->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Variant value updated successfully',
                'data' => $variantValeur
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating variant value: ' . $e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all(),
                'attribut_id' => $variantAttribut->id,
                'valeur_id' => $variantValeur->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating variant value',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified value
     */
    public function destroy(VariantAttribut $variantAttribut, VariantValeur $variantValeur): JsonResponse
    {
        try {
            // Ensure the value belongs to the attribute
            if ($variantValeur->attribut_id !== $variantAttribut->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Value does not belong to this attribute'
                ], 404);
            }

            $variantValeur->delete();

            return response()->json([
                'success' => true,
                'message' => 'Variant value deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting variant value'
            ], 500);
        }
    }

    /**
     * Reorder values for an attribute
     */
    public function reorder(Request $request, VariantAttribut $variantAttribut): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'orders' => 'required|array',
                'orders.*.id' => 'required|exists:variant_valeurs,id',
                'orders.*.ordre' => 'required|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            foreach ($request->get('orders') as $order) {
                $variantAttribut->valeurs()
                    ->where('id', $order['id'])
                    ->update(['ordre' => $order['ordre']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Values reordered successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reordering values'
            ], 500);
        }
    }
}
