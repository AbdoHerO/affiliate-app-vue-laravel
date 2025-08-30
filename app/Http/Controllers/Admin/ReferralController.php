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

        // Signup statistics using the new affiliate_parrained_by relationship
        $totalSignups = \App\Models\User::whereNotNull('affiliate_parrained_by')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $verifiedSignups = \App\Models\User::whereNotNull('affiliate_parrained_by')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('email_verifie', true)
            ->count();

        // Points awarded (from dispensations in date range)
        $totalPointsAwarded = ReferralDispensation::whereBetween('created_at', [$startDate, $endDate])
            ->sum('points') ?? 0;

        // Active referrers (affiliates with at least one referred user in date range)
        $activeReferrers = \App\Models\User::whereNotNull('affiliate_parrained_by')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('affiliate_parrained_by')
            ->count('affiliate_parrained_by');

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

        $query = \App\Models\User::whereNotNull('affiliate_parrained_by')
            ->with(['affiliateParrain.utilisateur:id,nom_complet,email']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('referrer_id')) {
            $query->where('affiliate_parrained_by', $request->referrer_id);
        }

        if ($request->has('verified')) {
            $query->where('email_verifie', $request->boolean('verified'));
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->start_date));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        // Note: Source filter removed as it's no longer applicable with the new system

        // Sort by most recent
        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $referredUsers = $query->paginate($perPage);

        // Transform the data to match the expected format
        $transformedData = $referredUsers->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'new_user_id' => $user->id,
                'new_user_name' => $user->nom_complet,
                'new_user_email' => $user->email,
                'new_user_phone' => $user->telephone,
                'email_verified' => $user->email_verifie,
                'affiliate_name' => $user->affiliateParrain?->utilisateur?->nom_complet ?? 'Unknown',
                'affiliate_email' => $user->affiliateParrain?->utilisateur?->email ?? 'N/A',
                'referrer_affiliate_id' => $user->affiliate_parrained_by,
                'attributed_at' => $user->created_at,
                'verified' => $user->email_verifie,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedData,
            'pagination' => [
                'current_page' => $referredUsers->currentPage(),
                'last_page' => $referredUsers->lastPage(),
                'per_page' => $referredUsers->perPage(),
                'total' => $referredUsers->total(),
            ],
        ]);
    }

    /**
     * Get top referrers for the leaderboard.
     */
    private function getTopReferrers(Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        return DB::table('users as u')
            ->join('profils_affilies as pa', 'u.affiliate_parrained_by', '=', 'pa.id')
            ->join('users as au', 'pa.utilisateur_id', '=', 'au.id')
            ->select([
                'pa.id as affiliate_id',
                'au.nom_complet as affiliate_name',
                'au.email as affiliate_email',
                DB::raw('COUNT(u.id) as total_referrals'),
                DB::raw('COUNT(CASE WHEN u.email_verifie = true THEN 1 END) as verified_referrals'),
                DB::raw('COALESCE(SUM(rd.points), 0) as total_points'),
            ])
            ->leftJoin('referral_dispensations as rd', 'pa.id', '=', 'rd.referrer_affiliate_id')
            ->whereBetween('u.created_at', [$startDate, $endDate])
            ->groupBy('pa.id', 'au.nom_complet', 'au.email')
            ->orderBy('verified_referrals', 'desc')
            ->orderBy('total_referrals', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
