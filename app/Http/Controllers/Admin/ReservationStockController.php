<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReservationService;
use App\Models\ReservationStock;
use App\Models\ProduitVariante;
use App\Models\Entrepot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ReservationStockController extends Controller
{
    protected ReservationService $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    /**
     * Display a listing of reservations
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReservationStock::with([
            'variante.produit:id,titre,slug',
            'entrepot:id,nom',
            'affilie:id,nom_complet',
            'offre:id,titre'
        ]);

        // Apply filters
        if ($request->filled('variante_id')) {
            $query->where('variante_id', $request->variante_id);
        }

        if ($request->filled('entrepot_id')) {
            $query->where('entrepot_id', $request->entrepot_id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('affilie_id')) {
            $query->where('affilie_id', $request->affilie_id);
        }

        // Search
        if ($request->filled('q')) {
            $search = $request->q;
            $query->whereHas('variante.produit', function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $reservations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reservations->items(),
            'pagination' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
            ]
        ]);
    }

    /**
     * Store a newly created reservation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variante_id' => 'required|uuid|exists:produit_variantes,id',
            'entrepot_id' => 'required|uuid|exists:entrepots,id',
            'quantite' => 'required|integer|min:1',
            'gamme_id' => 'nullable|uuid|exists:gammes_affilies,id',
            'affilie_id' => 'nullable|uuid|exists:profils_affilies,id',
            'offre_id' => 'nullable|uuid|exists:offres,id',
            'date_expire' => 'nullable|date|after:now',
            'note' => 'nullable|string|max:500'
        ]);

        try {
            $options = [];
            if ($validated['gamme_id'] ?? null) $options['gamme_id'] = $validated['gamme_id'];
            if ($validated['affilie_id'] ?? null) $options['affilie_id'] = $validated['affilie_id'];
            if ($validated['offre_id'] ?? null) $options['offre_id'] = $validated['offre_id'];
            if ($validated['date_expire'] ?? null) $options['expire_at'] = $validated['date_expire'];

            $reservation = $this->reservationService->createReservation(
                $validated['variante_id'],
                $validated['entrepot_id'],
                $validated['quantite'],
                $options
            );

            return response()->json([
                'success' => true,
                'message' => 'Réservation créée avec succès',
                'data' => $reservation->load(['variante.produit', 'entrepot', 'affilie', 'offre'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified reservation
     */
    public function show(ReservationStock $reservation): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $reservation->load([
                'variante.produit',
                'entrepot',
                'affilie',
                'offre',
                'gamme'
            ])
        ]);
    }

    /**
     * Release a reservation
     */
    public function release(ReservationStock $reservation, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:255'
        ]);

        try {
            $this->reservationService->releaseReservation(
                $reservation->id,
                $request->get('reason', 'manual_release')
            );

            return response()->json([
                'success' => true,
                'message' => 'Réservation annulée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Use a reservation (convert to stock movement)
     */
    public function use(ReservationStock $reservation, Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'nullable|string|max:255'
        ]);

        try {
            $this->reservationService->useReservation(
                $reservation->id,
                $request->get('reference')
            );

            return response()->json([
                'success' => true,
                'message' => 'Réservation utilisée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get reservation statistics
     */
    public function stats(): JsonResponse
    {
        $stats = $this->reservationService->getReservationStats();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Clean up expired reservations
     */
    public function cleanup(): JsonResponse
    {
        try {
            $count = $this->reservationService->cleanupExpiredReservations();

            return response()->json([
                'success' => true,
                'message' => "Nettoyage terminé. {$count} réservations expirées traitées.",
                'data' => ['cleaned_count' => $count]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get options for creating reservations
     */
    public function options(): JsonResponse
    {
        $variants = ProduitVariante::with('produit:id,titre')
            ->where('actif', true)
            ->get()
            ->map(function ($variant) {
                return [
                    'value' => $variant->id,
                    'title' => $variant->produit->titre . ' - ' . $variant->libelle,
                    'product_title' => $variant->produit->titre,
                    'variant_label' => $variant->libelle
                ];
            });

        $entrepots = Entrepot::where('actif', true)
            ->get(['id', 'nom'])
            ->map(function ($entrepot) {
                return [
                    'value' => $entrepot->id,
                    'title' => $entrepot->nom
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'variants' => $variants,
                'entrepots' => $entrepots,
                'statuts' => [
                    ['value' => 'active', 'title' => 'Active'],
                    ['value' => 'utilisee', 'title' => 'Utilisée'],
                    ['value' => 'expiree', 'title' => 'Expirée'],
                    ['value' => 'annulee', 'title' => 'Annulée']
                ]
            ]
        ]);
    }
}
