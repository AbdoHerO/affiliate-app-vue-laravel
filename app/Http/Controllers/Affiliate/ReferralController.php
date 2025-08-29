<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use App\Services\ReferralService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReferralController extends Controller
{
    public function __construct(
        protected ReferralService $referralService
    ) {}

    /**
     * Get referral dashboard for the authenticated affiliate.
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure user is an approved affiliate
        if (!$user->isApprovedAffiliate()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only approved affiliates can access referrals.',
            ], Response::HTTP_FORBIDDEN);
        }

        $affiliate = $user->profilAffilie;

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

        // Get referral statistics
        $stats = $this->referralService->getAffiliateStats($affiliate, $startDate, $endDate);

        // Get recent activity (last 10 events)
        $recentActivity = $this->getRecentActivity($affiliate, 10);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_activity' => $recentActivity,
                'date_range' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Get the affiliate's referral link.
     */
    public function getReferralLink(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure user is an approved affiliate
        if (!$user->isApprovedAffiliate()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only approved affiliates can access referrals.',
            ], Response::HTTP_FORBIDDEN);
        }

        $affiliate = $user->profilAffilie;
        $referralUrl = $this->referralService->generateReferralUrl($affiliate);
        $referralCode = $this->referralService->getOrCreateReferralCode($affiliate);

        return response()->json([
            'success' => true,
            'data' => [
                'referral_url' => $referralUrl,
                'referral_code' => $referralCode->code,
                'instructions' => [
                    'fr' => 'Partagez ce lien avec vos contacts. Ils ont 30 jours pour s\'inscrire après avoir cliqué.',
                    'en' => 'Share this link with your contacts. They have 30 days to sign up after clicking.',
                    'ar' => 'شارك هذا الرابط مع جهات الاتصال الخاصة بك. لديهم 30 يومًا للتسجيل بعد النقر.',
                ],
            ],
        ]);
    }

    /**
     * Get referred users for the authenticated affiliate.
     */
    public function getReferredUsers(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure user is an approved affiliate
        if (!$user->isApprovedAffiliate()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only approved affiliates can access referrals.',
            ], Response::HTTP_FORBIDDEN);
        }

        $affiliate = $user->profilAffilie;

        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50',
            'search' => 'nullable|string|max:255',
            'verified' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = ReferralAttribution::where('referrer_affiliate_id', $affiliate->id)
            ->with('newUser:id,nom_complet,email,email_verifie,created_at');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('newUser', function ($q) use ($search) {
                $q->where('nom_complet', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
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
            'data' => $referrals->items(),
            'pagination' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'per_page' => $referrals->perPage(),
                'total' => $referrals->total(),
            ],
        ]);
    }

    /**
     * Get dispensation history for the authenticated affiliate.
     */
    public function getDispensations(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure user is an approved affiliate
        if (!$user->isApprovedAffiliate()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only approved affiliates can access referrals.',
            ], Response::HTTP_FORBIDDEN);
        }

        $affiliate = $user->profilAffilie;

        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = ReferralDispensation::where('referrer_affiliate_id', $affiliate->id)
            ->select('id', 'points', 'comment', 'reference', 'created_at'); // Hide admin info

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->start_date));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        // Sort by most recent
        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $dispensations = $query->paginate($perPage);

        // Calculate total points
        $totalPoints = ReferralDispensation::where('referrer_affiliate_id', $affiliate->id)
            ->sum('points') ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'dispensations' => $dispensations->items(),
                'total_points' => $totalPoints,
                'pagination' => [
                    'current_page' => $dispensations->currentPage(),
                    'last_page' => $dispensations->lastPage(),
                    'per_page' => $dispensations->perPage(),
                    'total' => $dispensations->total(),
                ],
            ],
        ]);
    }

    /**
     * Get recent activity for the affiliate.
     * Note: Referred user activities are hidden for privacy - only showing points dispensations.
     */
    private function getRecentActivity($affiliate, int $limit = 10): array
    {
        $activities = [];

        // Only show recent dispensations (points awarded) - no referred user details for privacy
        $recentDispensations = ReferralDispensation::where('referrer_affiliate_id', $affiliate->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($recentDispensations as $dispensation) {
            $activities[] = [
                'type' => 'dispensation',
                'message' => '+' . $dispensation->points . ' points attribués',
                'date' => $dispensation->created_at,
                'comment' => $dispensation->comment,
            ];
        }

        // Sort all activities by date
        usort($activities, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return array_slice($activities, 0, $limit);
    }
}
