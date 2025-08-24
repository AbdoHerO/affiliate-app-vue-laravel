<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use App\Models\User;
use App\Models\ProfilAffilie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminReferralController extends Controller
{
    public function getDashboardStats(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        // Convert to Carbon instances for proper querying
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Total clicks in date range
        $totalClicks = ReferralClick::whereBetween('clicked_at', [$start, $end])->count();

        // Total signups in date range
        $totalSignups = ReferralAttribution::whereBetween('attributed_at', [$start, $end])->count();

        // Verified signups in date range
        $verifiedSignups = ReferralAttribution::whereBetween('attributed_at', [$start, $end])
            ->where('verified', true)
            ->count();

        // Conversion rate
        $conversionRate = $totalClicks > 0 ? round(($totalSignups / $totalClicks) * 100, 2) : 0;

        // Calculate total points earned (all time for better visibility)
        $clickPoints = ReferralClick::count() * 1;
        $signupPoints = ReferralAttribution::count() * 10;
        $verificationPoints = ReferralAttribution::where('verified', true)->count() * 50;
        $totalPoints = $clickPoints + $signupPoints + $verificationPoints;

        // Top performing affiliates
        $topAffiliates = ReferralAttribution::select('referrer_affiliate_id')
            ->selectRaw('COUNT(*) as total_referrals')
            ->selectRaw('SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified_referrals')
            ->whereBetween('attributed_at', [$start, $end])
            ->groupBy('referrer_affiliate_id')
            ->orderBy('total_referrals', 'desc')
            ->limit(10)
            ->with(['referrerAffiliate.utilisateur'])
            ->get()
            ->map(function ($item) {
                $affiliate = $item->referrerAffiliate;
                return [
                    'id' => $affiliate->id,
                    'name' => $affiliate->utilisateur->nom_complet ?? 'Unknown',
                    'email' => $affiliate->utilisateur->email ?? 'Unknown',
                    'total_referrals' => $item->total_referrals,
                    'verified_referrals' => $item->verified_referrals,
                    'conversion_rate' => $item->total_referrals > 0 ? 
                        round(($item->verified_referrals / $item->total_referrals) * 100, 1) : 0
                ];
            });

        // Recent activity (last 10 attributions)
        $recentActivity = ReferralAttribution::with(['newUser', 'referrerAffiliate.utilisateur'])
            ->whereBetween('attributed_at', [$start, $end])
            ->orderBy('attributed_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($attribution) {
                return [
                    'id' => $attribution->id,
                    'new_user_name' => $attribution->newUser->nom_complet ?? 'Unknown',
                    'new_user_email' => $attribution->newUser->email ?? 'Unknown',
                    'affiliate_name' => $attribution->referrerAffiliate->utilisateur->nom_complet ?? 'Unknown',
                    'referral_code' => $attribution->referral_code,
                    'verified' => $attribution->verified,
                    'attributed_at' => $attribution->attributed_at->format('Y-m-d H:i:s'),
                ];
            });

        // Count active referrers (affiliates with at least one attribution)
        $activeReferrers = ReferralAttribution::whereBetween('attributed_at', [$start, $end])
            ->distinct('referrer_affiliate_id')
            ->count('referrer_affiliate_id');

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_clicks' => $totalClicks,
                    'total_signups' => $totalSignups,
                    'verified_signups' => $verifiedSignups,
                    'conversion_rate' => $conversionRate,
                    'total_points_awarded' => $totalPoints,
                    'active_referrers' => $activeReferrers,
                ],
                'top_affiliates' => $topAffiliates,
                'recent_activity' => $recentActivity,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            ]
        ]);
    }

    public function getReferredUsers(Request $request)
    {
        $query = ReferralAttribution::with(['newUser', 'referrerAffiliate.utilisateur'])
            ->orderBy('attributed_at', 'desc');

        // Apply filters
        if ($request->has('affiliate_id') && $request->affiliate_id) {
            $query->where('referrer_affiliate_id', $request->affiliate_id);
        }

        if ($request->has('verified') && $request->verified !== '') {
            $query->where('verified', $request->verified === 'true');
        }

        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('attributed_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('attributed_at', '<=', $request->end_date);
        }

        $attributions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $attributions->items(),
            'pagination' => [
                'current_page' => $attributions->currentPage(),
                'last_page' => $attributions->lastPage(),
                'per_page' => $attributions->perPage(),
                'total' => $attributions->total(),
            ]
        ]);
    }

    public function getDispensations(Request $request)
    {
        $query = ReferralDispensation::with(['referrerAffiliate.utilisateur', 'createdByAdmin'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('affiliate_id') && $request->affiliate_id) {
            $query->where('referrer_affiliate_id', $request->affiliate_id);
        }

        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $dispensations = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $dispensations->items(),
            'pagination' => [
                'current_page' => $dispensations->currentPage(),
                'last_page' => $dispensations->lastPage(),
                'per_page' => $dispensations->perPage(),
                'total' => $dispensations->total(),
            ]
        ]);
    }

    public function createDispensation(Request $request)
    {
        $request->validate([
            'affiliate_id' => 'required|exists:profil_affilies,id',
            'points' => 'required|integer|min:1',
            'comment' => 'required|string|max:500',
            'reference' => 'nullable|string|max:100',
        ]);

        $dispensation = ReferralDispensation::create([
            'referrer_affiliate_id' => $request->affiliate_id,
            'created_by_admin_id' => auth()->id(),
            'points' => $request->points,
            'comment' => $request->comment,
            'reference' => $request->reference ?: 'ADMIN-' . strtoupper(uniqid()),
        ]);

        return response()->json([
            'message' => 'Dispensation created successfully',
            'data' => $dispensation->load(['referrerAffiliate.utilisateur', 'createdByAdmin'])
        ]);
    }

    public function getAffiliatesList()
    {
        $affiliates = ProfilAffilie::with('utilisateur')
            ->whereHas('utilisateur', function ($query) {
                $query->role('affiliate');
            })
            ->get()
            ->map(function ($profile) {
                return [
                    'id' => $profile->id,
                    'name' => $profile->utilisateur->nom_complet,
                    'email' => $profile->utilisateur->email,
                ];
            });

        return response()->json($affiliates);
    }
}
