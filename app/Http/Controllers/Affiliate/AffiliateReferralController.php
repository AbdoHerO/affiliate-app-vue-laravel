<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AffiliateReferralController extends Controller
{
    public function getDashboard(Request $request)
    {
        $affiliate = auth()->user()->profilAffilie;
        
        if (!$affiliate) {
            return response()->json(['error' => 'Affiliate profile not found'], 404);
        }

        $startDate = $request->get('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        // Convert to Carbon instances
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Get referral code
        $referralCode = ReferralCode::getOrCreateForAffiliate($affiliate);

        // Get clicks for this affiliate's code in date range
        $totalClicks = ReferralClick::where('referral_code', $referralCode->code)
            ->whereBetween('clicked_at', [$start, $end])
            ->count();

        // Get attributions for this affiliate in date range
        $totalSignups = ReferralAttribution::where('referrer_affiliate_id', $affiliate->id)
            ->whereBetween('attributed_at', [$start, $end])
            ->count();

        // Get verified signups
        $verifiedSignups = ReferralAttribution::where('referrer_affiliate_id', $affiliate->id)
            ->whereBetween('attributed_at', [$start, $end])
            ->where('verified', true)
            ->count();

        // Calculate conversion rate
        $conversionRate = $totalClicks > 0 ? round(($totalSignups / $totalClicks) * 100, 2) : 0;

        // Get total points from dispensations
        $totalPoints = ReferralDispensation::where('referrer_affiliate_id', $affiliate->id)
            ->whereBetween('created_at', [$start, $end])
            ->sum('points');

        // Get recent activity (last 10 attributions)
        $recentActivity = ReferralAttribution::with('newUser')
            ->where('referrer_affiliate_id', $affiliate->id)
            ->whereBetween('attributed_at', [$start, $end])
            ->orderBy('attributed_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($attribution) {
                return [
                    'id' => $attribution->id,
                    'user_name' => $attribution->newUser->nom_complet ?? 'Unknown',
                    'user_email' => $attribution->newUser->email ?? 'Unknown',
                    'verified' => $attribution->verified,
                    'attributed_at' => $attribution->attributed_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'stats' => [
                'total_clicks' => $totalClicks,
                'total_signups' => $totalSignups,
                'verified_signups' => $verifiedSignups,
                'conversion_rate' => $conversionRate,
                'total_points' => $totalPoints,
            ],
            'referral_code' => $referralCode->code,
            'referral_url' => url('/signup?ref=' . $referralCode->code),
            'recent_activity' => $recentActivity,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    public function getReferralLink()
    {
        $affiliate = auth()->user()->profilAffilie;
        
        if (!$affiliate) {
            return response()->json(['error' => 'Affiliate profile not found'], 404);
        }

        $referralCode = ReferralCode::getOrCreateForAffiliate($affiliate);

        return response()->json([
            'code' => $referralCode->code,
            'url' => url('/signup?ref=' . $referralCode->code),
            'created_at' => $referralCode->created_at,
        ]);
    }

    public function getReferredUsers(Request $request)
    {
        $affiliate = auth()->user()->profilAffilie;
        
        if (!$affiliate) {
            return response()->json(['error' => 'Affiliate profile not found'], 404);
        }

        $query = ReferralAttribution::with('newUser')
            ->where('referrer_affiliate_id', $affiliate->id)
            ->orderBy('attributed_at', 'desc');

        // Apply filters
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
        $affiliate = auth()->user()->profilAffilie;
        
        if (!$affiliate) {
            return response()->json(['error' => 'Affiliate profile not found'], 404);
        }

        $query = ReferralDispensation::with('createdByAdmin')
            ->where('referrer_affiliate_id', $affiliate->id)
            ->orderBy('created_at', 'desc');

        // Apply filters
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
}
