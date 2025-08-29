<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\ShippingParcel;
use App\Models\AuditLog;
use App\Events\OrderDelivered;
use App\Services\CommissionService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PreordersController extends Controller
{
    public function __construct(
        protected CommissionService $commissionService,
        protected OrderService $orderService
    ) {}
    /**
     * Display a listing of pre-orders (orders not yet shipped).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Commande::with([
            'boutique:id,nom',
            'affiliate:id,nom_complet,email', // Changed from affilie.utilisateur to affiliate
            'client:id,nom_complet,telephone',
            'clientFinal:id,nom_complet,telephone,email', // Add client final relationship
            'adresse:id,ville,adresse',
            'articles.produit:id,titre',
            'articles.variante:id,nom'
        ])
        ->select([
            'id', 'boutique_id', 'user_id', 'client_id', 'client_final_id', 'adresse_id', 'adresse_livraison_id', 'statut',
            'confirmation_cc', 'mode_paiement', 'type_command', 'total_ht', 'total_ttc', 'devise',
            'notes', 'no_answer_count', 'client_final_snapshot', 'created_at', 'updated_at'
        ])
        ->whereIn('statut', ['en_attente', 'confirmee', 'injoignable', 'refusee', 'annulee'])
        ->whereDoesntHave('shippingParcel');

        // Apply filters
        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->whereHas('client', function ($clientQuery) use ($search) {
                    $clientQuery->where('nom_complet', 'like', "%{$search}%")
                        ->orWhere('telephone', 'like', "%{$search}%");
                })
                ->orWhereHas('affilie.utilisateur', function ($affilieQuery) use ($search) {
                    $affilieQuery->where('nom_complet', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->get('statut'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        if ($request->filled('boutique_id')) {
            $query->where('boutique_id', $request->get('boutique_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        // Sorting
        $sortBy = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

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
     * Display the specified pre-order.
     */
    public function show(string $id): JsonResponse
    {
        $order = Commande::with([
            'boutique',
            'affiliate', // Changed from affilie.utilisateur to affiliate
            'client',
            'clientFinal', // Add client final relationship
            'adresse',
            'adresseLivraison', // Add delivery address relationship
            'articles.produit.images',
            'articles.variante',
            'offre',
            'shippingParcel'
        ])->findOrFail($id);

        // Add client final data to response
        $orderArray = $order->toArray();
        $orderArray['client_final_data'] = $this->orderService->getClientFinalData($order);

        return response()->json([
            'success' => true,
            'data' => $orderArray,
        ]);
    }

    /**
     * Update the specified pre-order.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $order = Commande::findOrFail($id);

        $validated = $request->validate([
            'statut' => 'sometimes|string|in:en_attente,confirmee',
            'confirmation_cc' => 'sometimes|string|in:non_contacte,a_confirmer,confirme,injoignable',
            'notes' => 'sometimes|string|nullable',
            'articles' => 'sometimes|array',
            'articles.*.id' => 'sometimes|exists:commande_articles,id',
            'articles.*.quantite' => 'sometimes|integer|min:1',
            'articles.*.prix_unitaire' => 'sometimes|numeric|min:0',
            // Client final validation
            'client' => 'sometimes|array',
            'client.nom_complet' => 'required_with:client|string|min:2|max:255',
            'client.telephone' => 'required_with:client|string|regex:/^[0-9+\-\s()]{10,}$/',
            'client.email' => 'sometimes|nullable|email|max:255',
            'client.adresse' => 'required_with:client|string|max:500',
            'client.ville' => 'required_with:client|string|max:100',
            'client.ville_id' => 'sometimes|nullable|string',
            'client.code_postal' => 'sometimes|nullable|string|max:20',
            'client.pays' => 'sometimes|string|max:2',
        ]);

        DB::transaction(function () use ($order, $validated) {
            // Update order fields (excluding articles and client)
            $order->update(collect($validated)->except(['articles', 'client'])->toArray());

            // Handle client final update if provided
            if (isset($validated['client'])) {
                $result = $this->orderService->updateOrderClientFinal($order, $validated['client']);
                if (!$result['success']) {
                    throw new \Exception($result['message']);
                }
            }

            // Update articles if provided
            if (isset($validated['articles'])) {
                foreach ($validated['articles'] as $articleData) {
                    if (isset($articleData['id'])) {
                        $article = $order->articles()->findOrFail($articleData['id']);
                        $article->update([
                            'quantite' => $articleData['quantite'] ?? $article->quantite,
                            'prix_unitaire' => $articleData['prix_unitaire'] ?? $article->prix_unitaire,
                            'total_ligne' => ($articleData['quantite'] ?? $article->quantite) *
                                           ($articleData['prix_unitaire'] ?? $article->prix_unitaire),
                        ]);
                    }
                }

                // Recalculate order totals
                $order->refresh();
                $totalHT = $order->articles->sum('total_ligne');
                $order->update([
                    'total_ht' => $totalHT,
                    'total_ttc' => $totalHT, // Assuming no tax for now
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->fresh(['articles.produit', 'articles.variante']),
        ]);
    }

    /**
     * Mark order as ready to ship (optional status).
     */
    public function confirm(string $id): JsonResponse
    {
        $order = Commande::findOrFail($id);

        if ($order->statut !== 'en_attente') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be confirmed',
            ], 422);
        }

        $order->update(['statut' => 'confirmee']);

        return response()->json([
            'success' => true,
            'message' => 'Order confirmed successfully',
            'data' => $order,
        ]);
    }

    /**
     * Bulk change status for multiple orders
     */
    public function bulkChangeStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:commandes,id',
            'to' => 'required|in:confirmee,injoignable,refusee,annulee',
            'note' => 'nullable|string|max:500'
        ]);

        $ids = $request->input('ids');
        $status = $request->input('to');
        $note = $request->input('note');

        // Get orders and validate they can be transitioned (not shipped)
        $orders = Commande::whereIn('id', $ids)
            ->whereDoesntHave('shippingParcel')
            ->get();

        if ($orders->count() !== count($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Certaines commandes ont déjà été expédiées et ne peuvent pas être modifiées'
            ], 422);
        }

        // Update orders
        $updated = [];
        foreach ($orders as $order) {
            $oldStatus = $order->statut;
            $order->statut = $status;

            if ($note) {
                $order->notes = $order->notes ? $order->notes . "\n" . $note : $note;
            }

            // Increment no_answer_count for injoignable status
            if ($status === 'injoignable') {
                $order->no_answer_count = ($order->no_answer_count ?? 0) + 1;
            }

            $order->save();

            // Handle commission calculation
            $this->handleCommissionStatusChange($order, $oldStatus, $status);

            $updated[] = $order->load([
                'boutique:id,nom',
                'affiliate:id,nom_complet,email',
                'client:id,nom_complet,telephone',
                'adresse:id,ville,adresse'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($updated) . ' commande(s) mise(s) à jour',
            'data' => $updated
        ]);
    }

    /**
     * Handle commission calculation based on order status change
     */
    protected function handleCommissionStatusChange(Commande $order, string $oldStatus, string $newStatus): void
    {
        try {
            $triggerStatus = \App\Models\AppSetting::get('commission.trigger_status', 'livree');
            $cancelStatuses = ['retournee', 'refusee', 'annulee'];

            // If order reaches trigger status, fire OrderDelivered event
            if ($newStatus === $triggerStatus && $oldStatus !== $triggerStatus) {
                OrderDelivered::dispatch($order, 'status_change', [
                    'previous_status' => $oldStatus,
                    'trigger' => 'preorder_status_change',
                ]);
            }

            // If order is canceled/returned, apply return policy
            if (in_array($newStatus, $cancelStatuses) && !in_array($oldStatus, $cancelStatuses)) {
                $this->commissionService->applyReturnPolicy($order);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the order status change
            \Illuminate\Support\Facades\Log::error('Commission handling failed for order status change', [
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Bulk send orders to shipping
     */
    public function bulkSendToShipping(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:commandes,id',
            'mode' => 'sometimes|string|in:ramassage,stock'
        ]);

        $ids = $request->input('ids');

        // Get confirmed orders that don't have shipping parcels
        $orders = Commande::whereIn('id', $ids)
            ->where('statut', 'confirmee')
            ->whereDoesntHave('shippingParcel')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune commande éligible pour l\'expédition'
            ], 422);
        }

        $mode = $request->input('mode', 'ramassage'); // Default to ramassage
        $stockValue = $mode === 'stock' ? '1' : '0';

        $results = [];
        $ozonService = app(\App\Services\OzonExpressService::class);

        foreach ($orders as $order) {
            try {
                $response = $ozonService->addParcel($order, $stockValue);

                if ($response['success']) {
                    $results[] = [
                        'order_id' => $order->id,
                        'success' => true,
                        'tracking_number' => $response['data']['tracking_number'] ?? null,
                        'exists' => $response['exists'] ?? false,
                        'mock' => $response['mock'] ?? false,
                    ];
                } else {
                    $results[] = [
                        'order_id' => $order->id,
                        'success' => false,
                        'error' => $response['message'] ?? 'Erreur inconnue'
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'order_id' => $order->id,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        $successCount = collect($results)->where('success', true)->count();
        $failureCount = collect($results)->where('success', false)->count();

        return response()->json([
            'success' => $successCount > 0,
            'message' => "{$successCount} commande(s) envoyée(s), {$failureCount} échec(s)",
            'data' => $results
        ]);
    }

    /**
     * Change status for a single order
     */
    public function changeStatus(Request $request, string $id)
    {
        $request->validate([
            'to' => 'required|in:confirmee,injoignable,refusee,annulee',
            'note' => 'nullable|string|max:500',
            'increment' => 'nullable|boolean'
        ]);

        $order = Commande::findOrFail($id);

        // Allow transitions from any status except shipped orders
        if ($order->shippingParcel) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande a déjà été expédiée et ne peut plus être modifiée'
            ], 422);
        }

        $status = $request->input('to');
        $note = $request->input('note');
        $increment = $request->input('increment', false);

        $oldStatus = $order->statut;

        // Use transaction for data integrity
        DB::transaction(function () use ($order, $status, $note, $increment) {
            $order->statut = $status;

            if ($note) {
                $order->notes = $order->notes ? $order->notes . "\n" . $note : $note;
            }

            // Increment no_answer_count for injoignable status or when explicitly requested
            if ($status === 'injoignable' || $increment) {
                $order->no_answer_count = ($order->no_answer_count ?? 0) + 1;
            }

            $order->save();
        });

        // Handle commission calculation based on status change
        $this->handleCommissionStatusChange($order, $oldStatus, $status);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data' => $order->load([
                'boutique:id,nom',
                'affiliate:id,nom_complet,email',
                'client:id,nom_complet,telephone',
                'adresse:id,ville,adresse'
            ])
        ]);
    }

    /**
     * Increment no answer count
     */
    public function incrementNoAnswer(string $id)
    {
        $order = Commande::findOrFail($id);

        $order->no_answer_count = ($order->no_answer_count ?? 0) + 1;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Compteur de non-réponse incrémenté',
            'data' => ['no_answer_count' => $order->no_answer_count]
        ]);
    }

    /**
     * Send single order to shipping
     */
    public function sendToShipping(Request $request, string $id)
    {
        $request->validate([
            'mode' => 'sometimes|string|in:ramassage,stock'
        ]);

        $order = Commande::findOrFail($id);

        if ($order->statut !== 'confirmee') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les commandes confirmées peuvent être expédiées'
            ], 422);
        }

        if ($order->shippingParcel) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande a déjà été expédiée'
            ], 422);
        }

        try {
            $mode = $request->input('mode', 'ramassage'); // Default to ramassage
            $stockValue = $mode === 'stock' ? '1' : '0';

            $ozonService = app(\App\Services\OzonExpressService::class);
            $response = $ozonService->addParcel($order, $stockValue);

            if ($response['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Commande envoyée vers OzonExpress',
                    'data' => $response['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'Erreur lors de l\'envoi'
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move order to shipping (local/manual delivery)
     */
    public function moveToShippingLocal(Request $request, string $id)
    {
        $request->validate([
            'note' => 'nullable|string|max:500'
        ]);

        $order = Commande::findOrFail($id);

        // Validate order state
        if ($order->statut !== 'confirmee') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les commandes confirmées peuvent être déplacées vers l\'expédition'
            ], 422);
        }

        // Check if already has shipping parcel
        if ($order->shippingParcel) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande a déjà été expédiée'
            ], 422);
        }

        try {
            DB::transaction(function () use ($order, $request) {
                // Create shipping parcel record for local delivery
                ShippingParcel::create([
                    'commande_id' => $order->id,
                    'provider' => 'local',
                    'tracking_number' => 'LOCAL-' . $order->id,
                    'status' => 'pending',
                    'sent_to_carrier' => false, // This is the key flag for local delivery
                    'receiver' => $order->clientFinal->nom_complet ?? $order->client->nom_complet,
                    'phone' => $order->clientFinal->telephone ?? $order->client->telephone,
                    'address' => $order->adresseLivraison->adresse ?? $order->adresse->adresse,
                    'city_name' => $order->adresseLivraison->ville ?? $order->adresse->ville,
                    'price' => $order->total_ttc,
                    'note' => $request->input('note', 'Livraison locale/manuelle'),
                    'last_synced_at' => now(),
                    'meta' => [
                        'local_delivery' => true,
                        'moved_to_shipping_at' => now()->toISOString(),
                        'moved_by' => request()->user()?->id,
                    ],
                ]);

                // Update order status to indicate it's in shipping
                $order->update([
                    'statut' => 'expediee'
                ]);

                // Create audit log entry
                AuditLog::create([
                    'auteur_id' => request()->user()?->id,
                    'action' => 'move_to_shipping_local',
                    'table_name' => 'commandes',
                    'record_id' => $order->id,
                    'old_values' => ['statut' => 'confirmee'],
                    'new_values' => [
                        'statut' => 'expediee',
                        'shipping_mode' => 'local',
                        'note' => $request->input('note'),
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Commande déplacée vers l\'expédition locale avec succès',
                'data' => [
                    'order_id' => $order->id,
                    'shipping_mode' => 'local',
                    'tracking_number' => 'LOCAL-' . $order->id,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du déplacement vers l\'expédition: ' . $e->getMessage()
            ], 500);
        }
    }
}
