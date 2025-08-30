<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\ShippingParcel;
use App\Models\AuditLog;
use App\Events\OrderDelivered;
use App\Services\StockService;
use App\Services\OrderService;
use App\Services\OrderStatusHistoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ShippingOrdersController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}
    /**
     * Display a listing of shipping orders (orders with parcels).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Commande::with([
            'boutique:id,nom',
            'affiliate:id,nom_complet,email', // Changed from affilie.utilisateur to affiliate
            'client:id,nom_complet,telephone',
            'clientFinal:id,nom_complet,telephone,email', // Add client final relationship
            'adresse:id,ville,adresse',
            'articles.produit:id,titre,sku',
            'articles.variante:id,nom',
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

        // Transform data to include client final information
        $transformedData = $orders->getCollection()->map(function ($order) {
            $clientFinalData = $this->orderService->getClientFinalData($order);

            $orderArray = $order->toArray();
            $orderArray['client_final_data'] = $clientFinalData;

            return $orderArray;
        });

        return response()->json([
            'success' => true,
            'data' => $transformedData,
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
            'affilie.utilisateur', // Load the legacy relationship with user
            'affiliate', // Also load the new relationship for compatibility
            'client',
            'clientFinal', // Add client final relationship
            'adresse',
            'adresseLivraison', // Add delivery address relationship
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

        // Add client final data to response
        $orderArray = $order->toArray();
        $orderArray['client_final_data'] = $this->orderService->getClientFinalData($order);

        return response()->json([
            'success' => true,
            'data' => $orderArray,
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

    /**
     * Resend order to OzonExpress (idempotent)
     */
    public function resendToOzon(Request $request): JsonResponse
    {
        $request->validate([
            'commande_id' => 'required|uuid|exists:commandes,id',
            'mode' => 'sometimes|string|in:ramassage,stock'
        ]);

        try {
            $commande = Commande::with(['client', 'adresse', 'articles.produit', 'shippingParcel'])
                ->findOrFail($request->commande_id);

            $mode = $request->input('mode', 'ramassage');
            $stockValue = $mode === 'stock' ? '1' : '0';

            $ozonService = app(\App\Services\OzonExpressService::class);
            $result = $ozonService->addParcel($commande, $stockValue);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order resent to OzonExpress successfully',
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to resend order'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error resending order to OzonExpress', [
                'commande_id' => $request->commande_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error resending order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track a parcel using OzonExpress API
     */
    public function trackParcel(Request $request): JsonResponse
    {
        $request->validate([
            'tracking_number' => 'required|string'
        ]);

        try {
            $ozonService = app(\App\Services\OzonExpressService::class);
            $result = $ozonService->track($request->tracking_number);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tracking information retrieved successfully',
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to retrieve tracking information'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error tracking parcel', [
                'tracking_number' => $request->tracking_number,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error tracking parcel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed parcel information
     */
    public function getParcelInfo(Request $request): JsonResponse
    {
        $request->validate([
            'tracking_number' => 'required|string'
        ]);

        try {
            $ozonService = app(\App\Services\OzonExpressService::class);
            $result = $ozonService->parcelInfo($request->tracking_number);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Parcel information retrieved successfully',
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to retrieve parcel information'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error getting parcel info', [
                'tracking_number' => $request->tracking_number,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting parcel info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new delivery note
     */
    public function createDeliveryNote(Request $request): JsonResponse
    {
        try {
            $ozonService = app(\App\Services\OzonExpressService::class);
            $result = $ozonService->dnCreate();

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Delivery note created successfully',
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to create delivery note'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error creating delivery note', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating delivery note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add parcels to delivery note
     */
    public function addParcelsToDeliveryNote(Request $request): JsonResponse
    {
        $request->validate([
            'ref' => 'required|string',
            'codes' => 'required|array|min:1',
            'codes.*' => 'required|string'
        ]);

        try {
            $ozonService = app(\App\Services\OzonExpressService::class);
            $result = $ozonService->dnAddParcels($request->ref, $request->codes);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Parcels added to delivery note successfully',
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to add parcels to delivery note'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error adding parcels to delivery note', [
                'ref' => $request->ref,
                'codes' => $request->codes,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error adding parcels to delivery note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save delivery note
     */
    public function saveDeliveryNote(Request $request): JsonResponse
    {
        $request->validate([
            'ref' => 'required|string'
        ]);

        try {
            $ozonService = app(\App\Services\OzonExpressService::class);
            $result = $ozonService->dnSave($request->ref);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Delivery note saved successfully',
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to save delivery note'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error saving delivery note', [
                'ref' => $request->ref,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error saving delivery note: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get delivery note PDF links
     */
    public function getDeliveryNotePdf(Request $request): JsonResponse
    {
        $request->validate([
            'ref' => 'required|string'
        ]);

        try {
            $ozonService = app(\App\Services\OzonExpressService::class);
            $result = $ozonService->dnGetPdf($request->ref);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'PDF links retrieved successfully',
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to get PDF links'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Error getting delivery note PDF', [
                'ref' => $request->ref,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error getting PDF links: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update shipping status manually (for local deliveries)
     */
    public function updateShippingStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,expediee,livree,refusee,retournee,annulee',
            'note' => 'nullable|string|max:500'
        ]);

        try {
            $order = Commande::with(['shippingParcel'])->findOrFail($id);

            if (!$order->shippingParcel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette commande n\'a pas de colis d\'expédition'
                ], 422);
            }

            // Only allow manual updates for local deliveries
            if ($order->shippingParcel->sent_to_carrier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les statuts des commandes envoyées au transporteur ne peuvent pas être modifiés manuellement'
                ], 422);
            }

            $newStatus = $request->input('status');
            $oldStatus = $order->shippingParcel->status;
            $note = $request->input('note');

            // Validate status transitions (more flexible for local deliveries)
            $validTransitions = [
                'pending' => ['expediee', 'livree', 'refusee', 'annulee'], // Allow direct delivery for local
                'expediee' => ['livree', 'refusee', 'retournee'],
                'livree' => ['retournee'], // Allow returns from delivered
                'refusee' => ['retournee', 'livree'], // Allow re-delivery after refusal
                'retournee' => ['livree'], // Allow re-delivery after return
                'annulee' => [], // Final state
            ];

            if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [])) {
                return response()->json([
                    'success' => false,
                    'message' => "Transition de statut invalide: {$oldStatus} → {$newStatus}. Transitions autorisées: " . implode(', ', $validTransitions[$oldStatus] ?? [])
                ], 422);
            }

            DB::transaction(function () use ($order, $newStatus, $oldStatus, $note) {
                // Update shipping parcel status
                $order->shippingParcel->update([
                    'status' => $newStatus,
                    'last_status_text' => $newStatus,
                    'last_status_at' => now(),
                    'last_synced_at' => now(),
                    'meta' => array_merge($order->shippingParcel->meta ?? [], [
                        'manual_status_updates' => array_merge(
                            $order->shippingParcel->meta['manual_status_updates'] ?? [],
                            [[
                                'from' => $oldStatus,
                                'to' => $newStatus,
                                'updated_at' => now()->toISOString(),
                                'updated_by' => request()->user()?->id,
                                'note' => $note,
                            ]]
                        )
                    ])
                ]);

                // Log shipping status change in order history
                $historyService = app(\App\Services\OrderStatusHistoryService::class);
                $historyService->logAdminChange(
                    $order,
                    $oldStatus,
                    $newStatus,
                    $note,
                    [
                        'type' => 'shipping_status_update',
                        'parcel_id' => $order->shippingParcel->id,
                        'manual_update' => true,
                    ]
                );

                // Update order status if needed
                if ($newStatus === 'livree') {
                    $order->update(['statut' => 'livree']);
                } elseif ($newStatus === 'refusee') {
                    $order->update(['statut' => 'refusee']);
                } elseif ($newStatus === 'retournee') {
                    $order->update(['statut' => 'retournee']);
                }

                // Create audit log entry
                AuditLog::create([
                    'auteur_id' => request()->user()?->id,
                    'action' => 'manual_shipping_status_update',
                    'table_name' => 'shipping_parcels',
                    'record_id' => $order->shippingParcel->id,
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => [
                        'status' => $newStatus,
                        'note' => $note,
                        'order_id' => $order->id,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                // Fire OrderDelivered event if status is delivered
                if ($newStatus === 'livree' && $oldStatus !== 'livree') {
                    OrderDelivered::dispatch($order, 'manual_update', [
                        'previous_status' => $oldStatus,
                        'updated_by' => request()->user()?->id,
                        'note' => $note,
                    ]);
                }
            });

            // Check if commissions were created (for debugging)
            $commissionsCount = $order->commissions()->count();

            return response()->json([
                'success' => true,
                'message' => "Statut mis à jour: {$oldStatus} → {$newStatus}" . ($newStatus === 'livree' ? ' (Commission créée automatiquement)' : ''),
                'data' => [
                    'order_id' => $order->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'commission_created' => $newStatus === 'livree',
                    'commissions_count' => $commissionsCount,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating shipping status manually', [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually decrement stock when shipping an order
     */
    public function decrementStock(Request $request, string $id): JsonResponse
    {
        try {
            $order = Commande::with(['articles.produit', 'articles.variante'])->findOrFail($id);

            // Check if order can be shipped
            if (!in_array($order->statut, ['confirmee', 'en_attente'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette commande ne peut pas être expédiée (statut: ' . $order->statut . ')'
                ], 422);
            }

            // Check if stock has already been decremented
            $existingMovements = \App\Models\MouvementStock::where('reference', 'ORDER_' . $order->id)
                ->where('type', 'out')
                ->exists();

            if ($existingMovements) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le stock a déjà été décrémenté pour cette commande'
                ], 422);
            }

            $stockService = app(StockService::class);
            $stockDetails = [];

            DB::transaction(function () use ($order, $stockService, &$stockDetails) {
                foreach ($order->articles as $article) {
                    // Get default warehouse for the product's boutique
                    $entrepot = $stockService->getDefaultEntrepot($article->produit->boutique_id);

                    if (!$entrepot) {
                        throw new \Exception("Aucun entrepôt trouvé pour la boutique: {$article->produit->boutique_id}");
                    }

                    // Check available stock
                    $stock = \App\Models\Stock::where('variante_id', $article->variante_id)
                        ->where('entrepot_id', $entrepot->id)
                        ->first();

                    $availableStock = $stock ? ($stock->qte_disponible - $stock->qte_reservee) : 0;

                    if ($availableStock < $article->quantite) {
                        throw new \Exception("Stock insuffisant pour {$article->produit->titre} - Variante {$article->variante->nom}: {$article->variante->valeur}. Disponible: {$availableStock}, Demandé: {$article->quantite}");
                    }

                    // Decrement stock
                    $result = $stockService->move(
                        $article->variante_id,
                        $entrepot->id,
                        'out',
                        $article->quantite,
                        'delivery_shipment',
                        "Stock décrémenté pour expédition manuelle de la commande",
                        'ORDER_' . $order->id,
                        request()->user()?->id
                    );

                    $stockDetails[] = [
                        'product' => $article->produit->titre,
                        'variant' => $article->variante->nom . ': ' . $article->variante->valeur,
                        'quantity_decremented' => $article->quantite,
                        'remaining_stock' => $availableStock - $article->quantite,
                    ];
                }

                // Update order status to shipped
                $order->update(['statut' => 'expediee']);

                // Create audit log
                AuditLog::create([
                    'auteur_id' => request()->user()?->id,
                    'action' => 'manual_stock_decrement_on_shipment',
                    'table_name' => 'commandes',
                    'record_id' => $order->id,
                    'new_values' => [
                        'stock_decremented_at' => now()->toISOString(),
                        'stock_details' => $stockDetails,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Stock décrémenté avec succès et commande expédiée',
                'data' => [
                    'order_id' => $order->id,
                    'new_status' => 'expediee',
                    'stock_details' => $stockDetails,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error decrementing stock for shipment', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du décrément du stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually increment stock when returning an order to warehouse
     */
    public function incrementStock(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'return_reason' => 'nullable|string|max:500'
        ]);

        try {
            $order = Commande::with(['articles.produit', 'articles.variante'])->findOrFail($id);

            // Check if order can be returned
            if (!in_array($order->statut, ['expediee', 'livree', 'refusee'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette commande ne peut pas être retournée (statut: ' . $order->statut . ')'
                ], 422);
            }

            // Check if stock was previously decremented
            $existingDecrements = \App\Models\MouvementStock::where('reference', 'ORDER_' . $order->id)
                ->where('type', 'out')
                ->exists();

            if (!$existingDecrements) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun décrément de stock trouvé pour cette commande'
                ], 422);
            }

            // Check if stock has already been incremented for return
            $existingReturns = \App\Models\MouvementStock::where('reference', 'RETURN_ORDER_' . $order->id)
                ->where('type', 'in')
                ->exists();

            if ($existingReturns) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le stock a déjà été ré-incrémenté pour le retour de cette commande'
                ], 422);
            }

            $stockService = app(StockService::class);
            $stockDetails = [];
            $returnReason = $request->input('return_reason', 'Retour en entrepôt');

            DB::transaction(function () use ($order, $stockService, $returnReason, &$stockDetails) {
                foreach ($order->articles as $article) {
                    // Get default warehouse for the product's boutique
                    $entrepot = $stockService->getDefaultEntrepot($article->produit->boutique_id);

                    if (!$entrepot) {
                        throw new \Exception("Aucun entrepôt trouvé pour la boutique: {$article->produit->boutique_id}");
                    }

                    // Get current stock before increment
                    $stock = \App\Models\Stock::where('variante_id', $article->variante_id)
                        ->where('entrepot_id', $entrepot->id)
                        ->first();

                    $currentStock = $stock ? $stock->qte_disponible : 0;

                    // Increment stock
                    $stockService->move(
                        $article->variante_id,
                        $entrepot->id,
                        'in',
                        $article->quantite,
                        'return',
                        "Stock ré-incrémenté pour retour en entrepôt: {$returnReason}",
                        'RETURN_ORDER_' . $order->id,
                        request()->user()?->id
                    );

                    $stockDetails[] = [
                        'product' => $article->produit->titre,
                        'variant' => $article->variante->nom . ': ' . $article->variante->valeur,
                        'quantity_incremented' => $article->quantite,
                        'new_stock' => $currentStock + $article->quantite,
                    ];
                }

                // Update order status to returned to warehouse
                $order->update(['statut' => 'returned_to_warehouse']);

                // Create audit log
                AuditLog::create([
                    'auteur_id' => request()->user()?->id,
                    'action' => 'manual_stock_increment_on_return',
                    'table_name' => 'commandes',
                    'record_id' => $order->id,
                    'new_values' => [
                        'stock_incremented_at' => now()->toISOString(),
                        'return_reason' => $returnReason,
                        'stock_details' => $stockDetails,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Stock ré-incrémenté avec succès et commande marquée comme retournée en entrepôt',
                'data' => [
                    'order_id' => $order->id,
                    'new_status' => 'returned_to_warehouse',
                    'return_reason' => $returnReason,
                    'stock_details' => $stockDetails,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error incrementing stock for return', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'incrémentation du stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order status timeline.
     */
    public function timeline(string $id): JsonResponse
    {
        try {
            $order = Commande::with(['statusHistory.changedBy:id,nom_complet,email'])
                ->findOrFail($id);

            $historyService = app(OrderStatusHistoryService::class);
            $timeline = $historyService->getOrderTimeline($order->id);

            return response()->json([
                'success' => true,
                'data' => $timeline->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'from_status' => $entry->from_status,
                        'to_status' => $entry->to_status,
                        'source' => $entry->source,
                        'source_label' => $entry->getSourceLabel(),
                        'status_label' => $entry->getStatusLabel(),
                        'note' => $entry->note,
                        'meta' => $entry->meta,
                        'created_at' => $entry->created_at,
                        'changed_by' => $entry->changedBy ? [
                            'id' => $entry->changedBy->id,
                            'name' => $entry->changedBy->nom_complet,
                            'email' => $entry->changedBy->email,
                        ] : null,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update delivery boy information for an order
     */
    public function updateDeliveryBoyInfo(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'delivery_boy_name' => 'nullable|string|max:255',
            'delivery_boy_phone' => 'nullable|string|max:20'
        ]);

        try {
            $order = Commande::findOrFail($id);

            $order->update([
                'delivery_boy_name' => $request->delivery_boy_name,
                'delivery_boy_phone' => $request->delivery_boy_phone
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Informations du livreur mises à jour avec succès',
                'data' => [
                    'delivery_boy_name' => $order->delivery_boy_name,
                    'delivery_boy_phone' => $order->delivery_boy_phone
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des informations du livreur: ' . $e->getMessage()
            ], 500);
        }
    }
}
