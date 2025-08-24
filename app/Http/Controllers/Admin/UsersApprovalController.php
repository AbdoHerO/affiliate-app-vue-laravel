<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Notifications\AffiliateApproved;
use App\Notifications\AffiliateRefused;

class UsersApprovalController extends Controller
{
    use AuthorizesRequests;
    /**
     * Get pending affiliate users for approval queue.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewApprovalQueue');
        $query = User::query()
            ->with(['roles'])
            ->withCount([
                'commandes as orders_count',
                'commissions as commissions_count'
            ])
            ->withSum('commissions as total_commissions', 'montant');

        // Apply filters
        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->get('approval_status'));
        }

        if ($request->filled('email_verified')) {
            if ($request->get('email_verified') === 'true') {
                $query->emailVerified();
            } else {
                $query->emailNotVerified();
            }
        }

        if ($request->filled('has_affiliate_role')) {
            if ($request->get('has_affiliate_role') === 'true') {
                $query->role('affiliate');
            } else {
                $query->whereDoesntHave('roles', function ($q) {
                    $q->where('name', 'affiliate');
                });
            }
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
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Approve a user as affiliate.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $this->authorize('approveUser');
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'sometimes|string|nullable',
        ]);

        DB::transaction(function () use ($user, $validated) {
            // Update approval status
            $user->update([
                'approval_status' => 'approved',
                'refusal_reason' => null,
            ]);

            // Assign affiliate role if not already assigned
            if (!$user->hasRole('affiliate')) {
                $user->assignRole('affiliate');
            }

            // Create affiliate profile if it doesn't exist
            if (!$user->profilAffilie) {
                $defaultTier = \App\Models\GammeAffilie::where('code', 'BASIC')->first();

                \App\Models\ProfilAffilie::create([
                    'utilisateur_id' => $user->id,
                    'gamme_id' => $defaultTier?->id,
                    'points' => 0,
                    'statut' => 'actif',
                    'notes_interne' => isset($validated['reason'])
                        ? 'Approuvé le ' . now()->format('Y-m-d H:i') . ': ' . $validated['reason']
                        : 'Approuvé le ' . now()->format('Y-m-d H:i'),
                ]);
            }

            // Update referral attribution to verified if exists and award verification points
            $attributions = \App\Models\ReferralAttribution::where('new_user_id', $user->id)
                ->where('verified', false)
                ->get();

            foreach ($attributions as $attribution) {
                $attribution->update([
                    'verified' => true,
                    'verified_at' => now(),
                ]);

                // Award verification points
                $autoPointsService = new \App\Services\AutoPointsDispensationService();
                $autoPointsService->awardVerificationPoints($attribution);
            }
        });

        // Send approval notification
        $user->notify(new AffiliateApproved($validated['reason'] ?? null));

        return response()->json([
            'success' => true,
            'message' => 'User approved as affiliate successfully',
            'data' => $user->fresh(['roles', 'profilAffilie.gamme']),
        ]);
    }

    /**
     * Refuse a user's affiliate application.
     */
    public function refuse(Request $request, string $id): JsonResponse
    {
        $this->authorize('refuseUser');
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $user->update([
            'approval_status' => 'refused',
            'refusal_reason' => $validated['reason'],
        ]);

        // Send refusal notification
        $user->notify(new AffiliateRefused($validated['reason']));

        return response()->json([
            'success' => true,
            'message' => 'User application refused',
            'data' => $user->fresh(['roles']),
        ]);
    }

    /**
     * Resend email verification.
     */
    public function resendVerification(string $id): JsonResponse
    {
        $this->authorize('resendVerification');
        $user = User::findOrFail($id);

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email is already verified',
            ], 422);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent successfully',
        ]);
    }

    /**
     * Get approval statistics.
     */
    public function getStats(): JsonResponse
    {
        $stats = [
            'pending_approval' => User::pendingApproval()->count(),
            'email_not_verified' => User::emailNotVerified()->count(),
            'approved_affiliates' => User::approved()->role('affiliate')->count(),
            'refused_applications' => User::refused()->count(),
            'recent_signups' => User::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
