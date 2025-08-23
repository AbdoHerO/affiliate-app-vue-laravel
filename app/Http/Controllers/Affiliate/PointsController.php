<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class PointsController extends Controller
{
    protected PointsService $pointsService;

    public function __construct(PointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }

    /**
     * Get points summary for the authenticated affiliate
     */
    public function getPointsSummary(): JsonResponse
    {
        try {
            $user = Auth::user();
            $affiliate = $user->profilAffilie;

            if (!$affiliate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Affiliate profile not found',
                ], 404);
            }

            $summary = $this->pointsService->getPointsSummary($affiliate);

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch points summary: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dispensation history for the authenticated affiliate
     */
    public function getDispensationHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $affiliate = $user->profilAffilie;

            if (!$affiliate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Affiliate profile not found',
                ], 404);
            }

            $perPage = $request->get('per_page', 15);
            $history = $this->pointsService->getDispensationHistory($affiliate, $perPage);

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dispensation history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed breakdown of how points were earned
     */
    public function getEarningsBreakdown(): JsonResponse
    {
        try {
            $user = Auth::user();
            $affiliate = $user->profilAffilie;

            if (!$affiliate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Affiliate profile not found',
                ], 404);
            }

            // Get attribution details
            $attributions = $affiliate->referralAttributions()
                ->with(['newUser:id,nom_complet,email,created_at'])
                ->orderBy('attributed_at', 'desc')
                ->get();

            $clicksCount = $affiliate->referralClicks()->count();
            $signupsCount = $attributions->count();
            $verifiedSignupsCount = $attributions->where('verified', true)->count();

            $breakdown = [
                'clicks' => [
                    'count' => $clicksCount,
                    'points_per_click' => PointsService::POINTS_PER_CLICK,
                    'total_points' => $clicksCount * PointsService::POINTS_PER_CLICK,
                ],
                'signups' => [
                    'count' => $signupsCount,
                    'points_per_signup' => PointsService::POINTS_PER_SIGNUP,
                    'total_points' => $signupsCount * PointsService::POINTS_PER_SIGNUP,
                ],
                'verified_signups' => [
                    'count' => $verifiedSignupsCount,
                    'points_per_verified' => PointsService::POINTS_PER_VERIFIED_SIGNUP,
                    'total_points' => $verifiedSignupsCount * PointsService::POINTS_PER_VERIFIED_SIGNUP,
                ],
                'recent_attributions' => $attributions->take(10)->map(function ($attribution) {
                    return [
                        'id' => $attribution->id,
                        'user' => $attribution->newUser,
                        'verified' => $attribution->verified,
                        'attributed_at' => $attribution->attributed_at,
                        'verified_at' => $attribution->verified_at,
                        'points_earned' => PointsService::POINTS_PER_SIGNUP + 
                            ($attribution->verified ? PointsService::POINTS_PER_VERIFIED_SIGNUP : 0),
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $breakdown,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch earnings breakdown: ' . $e->getMessage(),
            ], 500);
        }
    }
}
