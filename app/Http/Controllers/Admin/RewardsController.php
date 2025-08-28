<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilAffilie;
use App\Models\ReferralReward;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class RewardsController extends Controller
{
    /**
     * Get list of affiliates for rewards management
     */
    public function index(): JsonResponse
    {
        try {
            $affiliates = ProfilAffilie::with(['utilisateur:id,nom_complet,email'])
                ->withCount([
                    'referralAttributions as total_signups',
                    'referralAttributions as verified_signups' => function ($query) {
                        $query->where('verified', true);
                    }
                ])
                ->get()
                ->map(function ($affiliate) {
                    return [
                        'id' => $affiliate->id,
                        'nom_complet' => $affiliate->utilisateur->nom_complet,
                        'email' => $affiliate->utilisateur->email,
                        'profil_affilie' => [
                            'id' => $affiliate->id,
                            'points' => $affiliate->points ?? 0,
                        ],
                        'total_signups' => $affiliate->total_signups,
                        'verified_signups' => $affiliate->verified_signups,
                        'total_rewards' => $affiliate->referralRewards()->sum('points') ?? 0,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $affiliates,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch affiliates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new reward
     */
    public function createReward(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'affiliate_id' => 'required|exists:profils_affilies,id',
            'points' => 'required|integer|min:1|max:10000',
            'comment' => 'required|string|max:1000',
            'reference' => 'nullable|string|max:255',
        ]);

        try {
            $affiliate = ProfilAffilie::findOrFail($validated['affiliate_id']);
            $admin = Auth::user();

            // Create the reward
            $reward = ReferralReward::create([
                'referrer_affiliate_id' => $affiliate->id,
                'points' => $validated['points'],
                'comment' => $validated['comment'],
                'reference' => $validated['reference'] ?: 'REWARD-' . time(),
                'created_by_admin_id' => $admin->id,
            ]);

            // Update affiliate points
            $affiliate->increment('points', $validated['points']);

            return response()->json([
                'success' => true,
                'message' => 'Reward created successfully',
                'data' => [
                    'reward' => $reward->load(['createdByAdmin:id,nom_complet,email']),
                    'updated_points' => $affiliate->fresh()->points,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reward: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reward history for a specific affiliate
     */
    public function getRewardHistory(Request $request, string $affiliateId): JsonResponse
    {
        try {
            $affiliate = ProfilAffilie::findOrFail($affiliateId);
            
            $perPage = $request->get('per_page', 15);
            $rewards = ReferralReward::where('referrer_affiliate_id', $affiliate->id)
                ->with(['createdByAdmin:id,nom_complet,email'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'rewards' => $rewards->items(),
                    'pagination' => [
                        'current_page' => $rewards->currentPage(),
                        'last_page' => $rewards->lastPage(),
                        'per_page' => $rewards->perPage(),
                        'total' => $rewards->total(),
                    ],
                    'summary' => [
                        'total_rewards' => $affiliate->referralRewards()->sum('points') ?? 0,
                        'current_points' => $affiliate->points ?? 0,
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reward history: ' . $e->getMessage(),
            ], 500);
        }
    }
}
