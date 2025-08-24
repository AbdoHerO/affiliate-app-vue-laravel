<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfilAffilie;
use App\Models\ReferralAttribution;
use App\Models\ReferralClick;
use App\Models\ReferralDispensation;
use App\Services\ReferralService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    public function __construct(
        protected ReferralService $referralService
    ) {}

    /**
     * Get referral dashboard statistics.
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        // Total clicks (unique by IP per day)
        $totalClicks = ReferralClick::whereBetween('clicked_at', [$startDate, $endDate])
            ->selectRaw('COUNT(DISTINCT CONCAT(ip_hash, DATE(clicked_at))) as unique_clicks')
            ->value('unique_clicks') ?? 0;

        // Attribution statistics
        $totalSignups = ReferralAttribution::whereBetween('attributed_at', [$startDate, $endDate])->count();
        $verifiedSignups = ReferralAttribution::whereBetween('attributed_at', [$startDate, $endDate])
            ->where('verified', true)
            ->count();

        // Points awarded (from dispensations in date range)
        $totalPointsAwarded = ReferralDispensation::whereBetween('created_at', [$startDate, $endDate])
            ->sum('points') ?? 0;

        // Active referrers (affiliates with at least one attribution in date range)
        $activeReferrers = ReferralAttribution::whereBetween('attributed_at', [$startDate, $endDate])
            ->distinct('referrer_affiliate_id')
            ->count('referrer_affiliate_id');

        // Conversion rate
        $conversionRate = $totalClicks > 0 ? ($totalSignups / $totalClicks) * 100 : 0;
        $verifiedConversionRate = $totalClicks > 0 ? ($verifiedSignups / $totalClicks) * 100 : 0;

        // Top referrers
        $topReferrers = $this->getTopReferrers($startDate, $endDate, 10);

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_clicks' => $totalClicks,
                    'total_signups' => $totalSignups,
                    'verified_signups' => $verifiedSignups,
                    'conversion_rate' => round($conversionRate, 2),
                    'verified_conversion_rate' => round($verifiedConversionRate, 2),
                    'total_points_awarded' => $totalPointsAwarded,
                    'active_referrers' => $activeReferrers,
                ],
                'top_referrers' => $topReferrers,
                'date_range' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Get list of referred users with filters.
     */
    public function getReferredUsers(Request $request): JsonResponse
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'referrer_id' => 'nullable|uuid|exists:profils_affilies,id',
            'verified' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'source' => 'nullable|string|in:web,mobile',
        ]);

        $query = ReferralAttribution::with([
            'newUser:id,nom_complet,email,telephone,email_verifie,created_at',
            'referrerAffiliate.utilisateur:id,nom_complet,email',
        ]);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('newUser', function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('referrer_id')) {
            $query->where('referrer_affiliate_id', $request->referrer_id);
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

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Sort by most recent
        $query->orderBy('attributed_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $attributions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $attributions->items(),
            'pagination' => [
                'current_page' => $attributions->currentPage(),
                'last_page' => $attributions->lastPage(),
                'per_page' => $attributions->perPage(),
                'total' => $attributions->total(),
            ],
        ]);
    }

    /**
     * Get top referrers for the leaderboard.
     */
    private function getTopReferrers(Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        return DB::table('referral_attributions as ra')
            ->join('profils_affilies as pa', 'ra.referrer_affiliate_id', '=', 'pa.id')
            ->join('users as u', 'pa.utilisateur_id', '=', 'u.id')
            ->select([
                'pa.id as affiliate_id',
                'u.nom_complet as affiliate_name',
                'u.email as affiliate_email',
                DB::raw('COUNT(ra.id) as total_referrals'),
                DB::raw('COUNT(CASE WHEN ra.verified = true THEN 1 END) as verified_referrals'),
                DB::raw('COALESCE(SUM(rd.points), 0) as total_points'),
            ])
            ->leftJoin('referral_dispensations as rd', 'pa.id', '=', 'rd.referrer_affiliate_id')
            ->whereBetween('ra.attributed_at', [$startDate, $endDate])
            ->groupBy('pa.id', 'u.nom_complet', 'u.email')
            ->orderBy('verified_referrals', 'desc')
            ->orderBy('total_referrals', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
