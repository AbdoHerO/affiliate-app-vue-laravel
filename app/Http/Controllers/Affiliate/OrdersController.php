<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Affiliate\OrderResource;
use App\Models\Commande;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Display a listing of orders for the authenticated affiliate.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can view orders.',
                ], 403);
            }

            $query = Commande::with([
                'boutique:id,nom',
                'client:id,nom_complet,telephone',
                'clientFinal:id,nom_complet,telephone,email',
                'adresse:id,ville,adresse',
                'articles.produit:id,titre,sku',
                'articles.variante:id,nom',
                'shippingParcel',
                'expeditions'
            ])
            ->where('user_id', $user->id); // Scope to current affiliate only

            // Apply filters
            if ($request->filled('q')) {
                $search = $request->get('q');
                $query->where(function ($q) use ($search) {
                    $q->whereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('nom_complet', 'like', "%{$search}%")
                                   ->orWhere('telephone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('clientFinal', function ($clientQuery) use ($search) {
                        $clientQuery->where('nom_complet', 'like', "%{$search}%")
                                   ->orWhere('telephone', 'like', "%{$search}%");
                    })
                    ->orWhere('id', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $statuses = is_array($request->status) ? $request->status : [$request->status];
                $query->whereIn('statut', $statuses);
            }

            if ($request->filled('boutique_id')) {
                $query->where('boutique_id', $request->boutique_id);
            }

            if ($request->filled('date_from') || $request->filled('date_to')) {
                if ($request->filled('date_from')) {
                    $query->whereDate('created_at', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $query->whereDate('created_at', '<=', $request->date_to);
                }
            }

            // Apply sorting
            $sortBy = $request->get('sort', 'created_at');
            $sortDir = $request->get('dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Paginate
            $perPage = min($request->get('per_page', 15), 100);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => OrderResource::collection($orders),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching affiliate orders', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des commandes.',
            ], 500);
        }
    }

    /**
     * Display the specified order for the authenticated affiliate.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ensure user is an approved affiliate
            if (!$user->isApprovedAffiliate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only approved affiliates can view orders.',
                ], 403);
            }

            $order = Commande::with([
                'boutique:id,nom,adresse',
                'client:id,nom_complet,telephone,email',
                'clientFinal:id,nom_complet,telephone,email',
                'adresse:id,ville,adresse,code_postal',
                'articles.produit:id,titre,sku',
                'articles.variante:id,nom',
                'shippingParcel',
                'expeditions.evenements',
                'commissions' => function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                },
                'conflits',
                'retours'
            ])
            ->where('user_id', $user->id) // Ensure ownership
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée ou accès non autorisé.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error fetching affiliate order', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'order_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération de la commande.',
            ], 500);
        }
    }
}
