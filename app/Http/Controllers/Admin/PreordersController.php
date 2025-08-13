<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PreordersController extends Controller
{
    /**
     * Display a listing of pre-orders (orders not yet shipped).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Commande::with([
            'boutique:id,nom',
            'affiliate:id,nom_complet,email', // Changed from affilie.utilisateur to affiliate
            'client:id,nom_complet,telephone',
            'adresse:id,ville,adresse',
            'articles.produit:id,nom',
            'articles.variante:id,nom'
        ])
        ->whereIn('statut', ['en_attente', 'confirmee'])
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
     * Display the specified pre-order.
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
            'offre',
            'shippingParcel'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
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
        ]);

        DB::transaction(function () use ($order, $validated) {
            // Update order fields
            $order->update(collect($validated)->except('articles')->toArray());

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
}
