<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PointsService;
use App\Models\ProfilAffilie;
use App\Models\ReferralDispensation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

class ReferrersController extends Controller
{
    protected PointsService $pointsService;

    public function __construct(PointsService $pointsService)
    {
        $this->pointsService = $pointsService;
    }

    /**
     * Get list of referrers with points summary
     */
    public function index(): JsonResponse
    {
        try {
            $referrers = $this->pointsService->getAffiliatesWithPointsSummary();

            return response()->json([
                'success' => true,
                'data' => $referrers,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch referrers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new dispensation
     */
    public function createDispensation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'affiliate_id' => 'required|exists:profils_affilies,id',
            'points' => 'required|integer|min:1',
            'comment' => 'required|string|max:1000',
            'reference' => 'nullable|string|max:255',
        ]);

        try {
            $affiliate = ProfilAffilie::findOrFail($validated['affiliate_id']);
            $admin = Auth::user();

            $dispensation = $this->pointsService->createDispensation(
                $affiliate,
                $validated['points'],
                $validated['comment'],
                $admin,
                $validated['reference']
            );

            // Get updated summary for the affiliate
            $updatedSummary = $this->pointsService->getPointsSummary($affiliate);

            return response()->json([
                'success' => true,
                'message' => 'Dispensation created successfully',
                'data' => [
                    'dispensation' => $dispensation->load(['createdByAdmin:id,nom_complet,email']),
                    'updated_summary' => $updatedSummary,
                ],
            ]);
        } catch (Exception $e) {
            $statusCode = 422;
            
            // Check for specific error types
            if (str_contains($e->getMessage(), 'Insufficient balance')) {
                $statusCode = 422;
            } elseif (str_contains($e->getMessage(), 'required')) {
                $statusCode = 422;
            } else {
                $statusCode = 500;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get dispensation history for a specific affiliate
     */
    public function getDispensationHistory(Request $request, string $affiliateId): JsonResponse
    {
        try {
            $affiliate = ProfilAffilie::findOrFail($affiliateId);
            
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
     * Get points summary for a specific affiliate
     */
    public function getPointsSummary(string $affiliateId): JsonResponse
    {
        try {
            $affiliate = ProfilAffilie::findOrFail($affiliateId);
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
     * Get overall statistics for the admin dashboard
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $referrers = $this->pointsService->getAffiliatesWithPointsSummary();
            
            $stats = [
                'total_referrers' => count($referrers),
                'total_points_earned' => array_sum(array_column($referrers, 'points_earned')),
                'total_points_dispensed' => array_sum(array_column($referrers, 'points_dispensed')),
                'total_points_balance' => array_sum(array_column($referrers, 'points_balance')),
                'total_verified_signups' => array_sum(array_column($referrers, 'verified_signups')),
                'total_signups' => array_sum(array_column($referrers, 'total_signups')),
                'recent_dispensations' => ReferralDispensation::with([
                    'referrerAffiliate.utilisateur:id,nom_complet,email',
                    'createdByAdmin:id,nom_complet,email'
                ])
                ->latest()
                ->take(5)
                ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
}
