<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ShippingOrdersController extends Controller
{
    /**
     * Display a listing of shipping orders (orders with parcels).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Commande::with([
            'boutique:id,nom',
            'affiliate:id,nom_complet,email', // Changed from affilie.utilisateur to affiliate
            'client:id,nom_complet,telephone',
            'adresse:id,ville,adresse',
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

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
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
            'affiliate', // Changed from affilie.utilisateur to affiliate
            'client',
            'adresse',
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

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }
}
