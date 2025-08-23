<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ProfilAffilie;
use App\Models\GammeAffilie;
use App\Models\ProfilAffilieGammeHisto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AffiliatesController extends Controller
{
    /**
     * Display a listing of affiliates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::role('affiliate')
            ->with([
                'profilAffilie.gamme:id,code,libelle',
                'kycDocuments' => function ($q) {
                    $q->select('utilisateur_id', 'statut')->latest();
                }
            ])
            ->withCount([
                'commissions as commissions_count'
            ])
            ->withSum('commissions as total_commissions', 'amount');

        // Apply filters
        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->get('statut'));
        }

        if ($request->filled('affiliate_status')) {
            $query->whereHas('profilAffilie', function ($q) use ($request) {
                $q->where('statut', $request->get('affiliate_status'));
            });
        }

        if ($request->filled('gamme_id')) {
            $query->whereHas('profilAffilie', function ($q) use ($request) {
                $q->where('gamme_id', $request->get('gamme_id'));
            });
        }

        if ($request->filled('kyc_statut')) {
            $query->where('kyc_statut', $request->get('kyc_statut'));
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

        if ($sortBy === 'total_commissions') {
            $query->orderBy('total_commissions', $sortDir);
        } elseif ($sortBy === 'orders_count') {
            $query->orderBy('orders_count', $sortDir);
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        // Pagination
        $perPage = $request->get('perPage', 15);
        $affiliates = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $affiliates->items(),
            'pagination' => [
                'current_page' => $affiliates->currentPage(),
                'last_page' => $affiliates->lastPage(),
                'per_page' => $affiliates->perPage(),
                'total' => $affiliates->total(),
            ],
        ]);
    }

    /**
     * Display the specified affiliate.
     */
    public function show(string $id): JsonResponse
    {
        $affiliate = User::role('affiliate')
            ->with([
                'profilAffilie.gamme',
                'profilAffilie.gammeHistorique.gamme:id,code,libelle',
                // Note: commandes relationship loaded separately if needed
                'commissions' => function ($q) {
                    $q->latest()->take(10);
                },
                'profilAffilie.reglements' => function ($q) {
                    $q->latest()->take(5);
                },
                'kycDocuments',
                'filleuls.filleul:id,nom_complet,email',
                'parrain.parrain:id,nom_complet,email'
            ])
            ->withCount([
                'commissions as commissions_count'
            ])
            ->withSum('commissions as total_commissions', 'amount')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $affiliate,
        ]);
    }

    /**
     * Update the specified affiliate.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $affiliate = User::role('affiliate')->findOrFail($id);

        $validated = $request->validate([
            'nom_complet' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'telephone' => 'sometimes|string|max:20',
            'adresse' => 'sometimes|string|nullable',
            'statut' => 'sometimes|string|in:actif,inactif,bloque',
            'kyc_statut' => 'sometimes|string|in:non_requis,en_attente,valide,refuse',
            'profile' => 'sometimes|array',
            'profile.statut' => 'sometimes|string|in:actif,suspendu,resilie',
            'profile.gamme_id' => 'sometimes|exists:gammes_affilies,id',
            'profile.rib' => 'sometimes|string|nullable',
            'profile.notes_interne' => 'sometimes|string|nullable',
        ]);

        DB::transaction(function () use ($affiliate, $validated) {
            // Update user fields
            $userFields = collect($validated)->except('profile')->toArray();
            if (!empty($userFields)) {
                $affiliate->update($userFields);
            }

            // Update profile fields
            if (isset($validated['profile'])) {
                $profileData = $validated['profile'];

                // If changing tier, record history
                if (isset($profileData['gamme_id']) &&
                    $affiliate->profilAffilie->gamme_id !== $profileData['gamme_id']) {

                    // Close current tier history
                    ProfilAffilieGammeHisto::where('profil_id', $affiliate->profilAffilie->id)
                        ->whereNull('date_fin')
                        ->update(['date_fin' => now()]);

                    // Create new tier history
                    ProfilAffilieGammeHisto::create([
                        'profil_id' => $affiliate->profilAffilie->id,
                        'gamme_id' => $profileData['gamme_id'],
                        'date_debut' => now(),
                    ]);
                }

                $affiliate->profilAffilie->update($profileData);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Affiliate updated successfully',
            'data' => $affiliate->fresh(['profilAffilie.gamme']),
        ]);
    }

    /**
     * Block/unblock an affiliate.
     */
    public function toggleBlock(Request $request, string $id): JsonResponse
    {
        $affiliate = User::role('affiliate')->findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|string|in:block,unblock',
            'reason' => 'sometimes|string|nullable',
        ]);

        $newStatus = $validated['action'] === 'block' ? 'bloque' : 'actif';
        $profileStatus = $validated['action'] === 'block' ? 'suspendu' : 'actif';

        DB::transaction(function () use ($affiliate, $newStatus, $profileStatus, $validated) {
            $affiliate->update(['statut' => $newStatus]);
            $affiliate->profilAffilie->update([
                'statut' => $profileStatus,
                'notes_interne' => isset($validated['reason'])
                    ? ($affiliate->profilAffilie->notes_interne . "\n" . now()->format('Y-m-d H:i') . ": " . $validated['reason'])
                    : $affiliate->profilAffilie->notes_interne
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => $validated['action'] === 'block'
                ? 'Affiliate blocked successfully'
                : 'Affiliate unblocked successfully',
            'data' => $affiliate->fresh(['profilAffilie.gamme']),
        ]);
    }

    /**
     * Change affiliate tier.
     */
    public function changeTier(Request $request, string $id): JsonResponse
    {
        $affiliate = User::role('affiliate')->findOrFail($id);

        $validated = $request->validate([
            'gamme_id' => 'required|exists:gammes_affilies,id',
            'reason' => 'sometimes|string|nullable',
        ]);

        if ($affiliate->profilAffilie->gamme_id === $validated['gamme_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate is already in this tier',
            ], 422);
        }

        DB::transaction(function () use ($affiliate, $validated) {
            // Close current tier history
            ProfilAffilieGammeHisto::where('profil_id', $affiliate->profilAffilie->id)
                ->whereNull('date_fin')
                ->update(['date_fin' => now()]);

            // Create new tier history
            ProfilAffilieGammeHisto::create([
                'profil_id' => $affiliate->profilAffilie->id,
                'gamme_id' => $validated['gamme_id'],
                'date_debut' => now(),
            ]);

            // Update profile
            $affiliate->profilAffilie->update([
                'gamme_id' => $validated['gamme_id'],
                'notes_interne' => isset($validated['reason'])
                    ? ($affiliate->profilAffilie->notes_interne . "\n" . now()->format('Y-m-d H:i') . ": Tier changed - " . $validated['reason'])
                    : $affiliate->profilAffilie->notes_interne
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Affiliate tier changed successfully',
            'data' => $affiliate->fresh(['profilAffilie.gamme']),
        ]);
    }

    /**
     * Get affiliate performance statistics.
     */
    public function getPerformance(string $id): JsonResponse
    {
        $affiliate = User::role('affiliate')->findOrFail($id);

        $stats = [
            'orders' => [
                'total' => $affiliate->profilAffilie->commandes()->count(),
                'this_month' => $affiliate->profilAffilie->commandes()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'by_status' => $affiliate->profilAffilie->commandes()
                    ->select('statut', DB::raw('count(*) as count'))
                    ->groupBy('statut')
                    ->pluck('count', 'statut'),
            ],
            'commissions' => [
                'total' => $affiliate->commissions()->sum('amount'),
                'this_month' => $affiliate->commissions()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('amount'),
                'by_status' => $affiliate->commissions()
                    ->select('status', DB::raw('sum(amount) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status'),
            ],
            'payments' => [
                'total_paid' => $affiliate->profilAffilie->reglements()
                    ->where('statut', 'paye')
                    ->sum('montant'),
                'pending' => $affiliate->profilAffilie->reglements()
                    ->where('statut', 'en_attente')
                    ->sum('montant'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get available affiliate tiers.
     */
    public function getTiers(): JsonResponse
    {
        $tiers = GammeAffilie::where('actif', true)
            ->select('id', 'code', 'libelle')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tiers,
        ]);
    }
}
