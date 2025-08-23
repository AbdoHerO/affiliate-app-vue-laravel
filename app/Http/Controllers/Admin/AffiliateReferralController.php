<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilAffilie;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use App\Services\ReferralService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AffiliateReferralController extends Controller
{
    public function __construct(
        protected ReferralService $referralService
    ) {}

    /**
     * Get detailed referral information for a specific affiliate.
     */
    public function show(Request $request, string $affiliateId): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $affiliate = ProfilAffilie::with('utilisateur:id,nom_complet,email,telephone')
            ->findOrFail($affiliateId);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

        // Get referral statistics
        $stats = $this->referralService->getAffiliateStats($affiliate, $startDate, $endDate);

        // Get recent referred users
        $recentReferrals = ReferralAttribution::where('referrer_affiliate_id', $affiliateId)
            ->with('newUser:id,nom_complet,email,telephone,email_verifie,created_at')
            ->withinDateRange($startDate, $endDate)
            ->orderBy('attributed_at', 'desc')
            ->limit(20)
            ->get();

        // Get dispensation history
        $dispensations = ReferralDispensation::where('referrer_affiliate_id', $affiliateId)
            ->with('createdByAdmin:id,nom_complet,email')
            ->withinDateRange($startDate, $endDate)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get monthly breakdown
        $monthlyBreakdown = $this->getMonthlyBreakdown($affiliateId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => [
                'affiliate' => $affiliate,
                'stats' => $stats,
                'recent_referrals' => $recentReferrals,
                'dispensations' => $dispensations,
                'monthly_breakdown' => $monthlyBreakdown,
                'date_range' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Get referred users for a specific affiliate.
     */
    public function getReferredUsers(Request $request, string $affiliateId): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'verified' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $affiliate = ProfilAffilie::findOrFail($affiliateId);

        $query = ReferralAttribution::where('referrer_affiliate_id', $affiliateId)
            ->with('newUser:id,nom_complet,email,telephone,email_verifie,created_at');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('newUser', function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        if ($request->has('verified')) {
            $query->where('verified', $request->boolean('verified'));
        }

        if ($request->filled('start_date')) {
            $query->where('attributed_at', '>=', Carbon::parse($request->start_date));
        }

        if ($request->filled('end_date')) {
            $query->where('attributed_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        // Sort by most recent
        $query->orderBy('attributed_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $referrals = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'affiliate' => $affiliate->load('utilisateur:id,nom_complet,email'),
                'referrals' => $referrals->items(),
                'pagination' => [
                    'current_page' => $referrals->currentPage(),
                    'last_page' => $referrals->lastPage(),
                    'per_page' => $referrals->perPage(),
                    'total' => $referrals->total(),
                ],
            ],
        ]);
    }

    /**
     * Get affiliate referral performance comparison.
     */
    public function getPerformanceComparison(Request $request): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'integer|min:5|max:50',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();
        $limit = $request->get('limit', 20);

        // Get top performing affiliates
        $topPerformers = ProfilAffilie::with('utilisateur:id,nom_complet,email')
            ->withCount([
                'referralAttributions as total_referrals' => function ($query) use ($startDate, $endDate) {
                    $query->withinDateRange($startDate, $endDate);
                },
                'referralAttributions as verified_referrals' => function ($query) use ($startDate, $endDate) {
                    $query->withinDateRange($startDate, $endDate)->where('verified', true);
                },
            ])
            ->withSum([
                'referralDispensations as total_points' => function ($query) use ($startDate, $endDate) {
                    $query->withinDateRange($startDate, $endDate);
                }
            ], 'points')
            ->having('total_referrals', '>', 0)
            ->orderBy('verified_referrals', 'desc')
            ->orderBy('total_referrals', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'top_performers' => $topPerformers,
                'date_range' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Get monthly breakdown of referral performance.
     */
    private function getMonthlyBreakdown(string $affiliateId, Carbon $startDate, Carbon $endDate): array
    {
        $months = [];
        $current = $startDate->copy()->startOfMonth();
        $end = $endDate->copy()->endOfMonth();

        while ($current <= $end) {
            $monthStart = $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();

            $attributions = ReferralAttribution::where('referrer_affiliate_id', $affiliateId)
                ->withinDateRange($monthStart, $monthEnd);

            $dispensations = ReferralDispensation::where('referrer_affiliate_id', $affiliateId)
                ->withinDateRange($monthStart, $monthEnd);

            $months[] = [
                'month' => $current->format('Y-m'),
                'month_name' => $current->format('F Y'),
                'total_referrals' => $attributions->count(),
                'verified_referrals' => $attributions->where('verified', true)->count(),
                'points_awarded' => $dispensations->sum('points') ?? 0,
            ];

            $current->addMonth();
        }

        return $months;
    }
}
