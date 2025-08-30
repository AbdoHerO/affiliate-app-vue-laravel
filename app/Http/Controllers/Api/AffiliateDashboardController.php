<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Models\ReferralAttribution;
use App\Models\ReferralCode;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AffiliateDashboardController extends Controller
{
    /**
     * Get affiliate dashboard statistics
     */
    public function getStats(Request $request)
    {
        $user = $request->user();
        
        // Check affiliate permission
        if (!$user->hasRole('affiliate')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $cacheKey = 'affiliate_dashboard_stats_' . $user->id . '_' . md5(serialize($filters));

        $cards = Cache::remember($cacheKey, 300, function () use ($user, $filters) {
            return $this->getKpiCards($user, $filters);
        });

        return response()->json([
            'success' => true,
            'data' => ['cards' => $cards],
        ]);
    }

    /**
     * Get chart data for affiliate dashboard
     */
    public function getChartData(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasRole('affiliate')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $period = $request->get('period', 'month'); // day, week, month, year

        $chartType = $request->get('type');
        $cacheKey = 'affiliate_dashboard_chart_' . $chartType . '_' . $user->id . '_' . md5(serialize($filters));

        $chartData = Cache::remember($cacheKey, 300, function () use ($chartType, $user, $filters) {
            switch ($chartType) {
                case 'top_products_sold':
                    return $this->getMyTopProductsChart($user, $filters);
                default:
                    return ['error' => 'Invalid chart type'];
            }
        });

        return response()->json([
            'success' => true,
            'data' => $chartData,
        ]);
    }

    /**
     * Get table data for affiliate dashboard
     */
    public function getTableData(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasRole('affiliate')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $type = $request->get('type');
        $filters = $this->parseFilters($request);

        switch ($type) {
            case 'my_recent_orders':
                $data = $this->getMyRecentOrders($user, $filters);
                break;
            case 'my_recent_payments':
                $data = $this->getMyRecentPayments($user, $filters);
                break;
            case 'my_active_referrals':
                $data = $this->getMyActiveReferrals($user, $filters);
                break;
            default:
                return response()->json(['message' => 'Invalid table type'], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get or create referral link for affiliate
     */
    public function getReferralLink(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasRole('affiliate')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $affiliateProfile = $user->profilAffilie;
        if (!$affiliateProfile) {
            return response()->json(['message' => 'Affiliate profile not found'], Response::HTTP_NOT_FOUND);
        }

        $referralCode = ReferralCode::getOrCreateForAffiliate($affiliateProfile);
        $baseUrl = config('app.url');
        $referralLink = "{$baseUrl}/ref/{$referralCode->code}";

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $referralCode->code,
                'link' => $referralLink,
                'active' => $referralCode->active,
                'created_at' => $referralCode->created_at,
            ],
        ]);
    }

    /**
     * Parse request filters
     */
    private function parseFilters(Request $request): array
    {
        $dateStart = $request->get('date_start');
        $dateEnd = $request->get('date_end');
        // Support front-end dateRange JSON param: {"start":"YYYY-MM-DD","end":"YYYY-MM-DD"}
        if (!$dateStart && !$dateEnd && $request->has('dateRange')) {
            try {
                $range = $request->get('dateRange');
                if (is_string($range)) {
                    $decoded = json_decode($range, true);
                } else {
                    $decoded = $range; // already array from frontend maybe
                }
                if (is_array($decoded)) {
                    $dateStart = $decoded['start'] ?? null;
                    $dateEnd = $decoded['end'] ?? null;
                }
            } catch (\Throwable $e) {
                // Ignore malformed dateRange
            }
        }

        $dateStart = $dateStart ?: Carbon::now()->startOfMonth()->toDateString();
        $dateEnd = $dateEnd ?: Carbon::now()->toDateString();

        return [
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'period' => $request->get('period', 'month'), // Add period parameter
            'status' => $request->get('status'),
            'page' => (int) $request->get('page', 1),
            'per_page' => min((int) $request->get('per_page', 15), 100),
        ];
    }

    /**
     * Get KPI cards data for affiliate (5 indicators) - Unified response format
     */
    private function getKpiCards(User $user, array $filters): array
    {
        $thisMonth = Carbon::now()->startOfMonth();

        $receivedPayments = Withdrawal::where('user_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount') ?? 0;
        $pendingPayments = Withdrawal::where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount') ?? 0;

        return [
            [
                'key' => 'total_orders',
                'labelKey' => 'dashboard.affiliate.cards.total_orders',
                'value' => Commande::where('user_id', $user->id)->count()
            ],
            [
                'key' => 'total_commissions',
                'labelKey' => 'dashboard.affiliate.cards.total_commissions',
                'value' => (float) (CommissionAffilie::where('user_id', $user->id)->sum('amount') ?? 0)
            ],
            [
                'key' => 'monthly_earnings',
                'labelKey' => 'dashboard.affiliate.cards.monthly_earnings',
                'value' => (float) (CommissionAffilie::where('user_id', $user->id)
                    ->where('created_at', '>=', $thisMonth)
                    ->sum('amount') ?? 0)
            ],
            [
                'key' => 'payments_status',
                'labelKey' => 'dashboard.affiliate.cards.payments_status',
                'value' => [
                    'received' => (float) $receivedPayments,
                    'pending' => (float) $pendingPayments
                ]
            ],
            [
                'key' => 'pending_tickets',
                'labelKey' => 'dashboard.affiliate.cards.pending_tickets',
                'value' => Ticket::where('requester_id', $user->id)->where('status', 'open')->count()
            ]
        ];
    }

    /**
     * Get performance statistics for affiliate
     */
    private function getPerformanceStats(User $user, array $filters): array
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        
        $affiliateProfile = $user->profilAffilie;
        $referralCode = $affiliateProfile?->referralCode;

        // This month metrics
        $clicksThisMonth = $referralCode ? 
            ReferralClick::where('referral_code', $referralCode->code)
                ->where('created_at', '>=', $thisMonth)
                ->count() : 0;

        $signupsThisMonth = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)
            ->where('created_at', '>=', $thisMonth)
            ->count();

        $verifiedSignupsThisMonth = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)
            ->where('verified', true)
            ->where('created_at', '>=', $thisMonth)
            ->count();

        $ordersThisMonth = Commande::where('user_id', $user->id)
            ->where('created_at', '>=', $thisMonth)
            ->count();

        $commissionsThisMonth = CommissionAffilie::where('user_id', $user->id)
            ->where('created_at', '>=', $thisMonth)
            ->sum('amount') ?? 0;

        // Last month for trends
        $commissionsLastMonth = CommissionAffilie::where('user_id', $user->id)
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->sum('amount') ?? 0;

        $clicksLastMonth = $referralCode ? 
            ReferralClick::where('referral_code', $referralCode->code)
                ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
                ->count() : 0;

        $signupsLastMonth = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        // Calculate trends
        $commissionsTrend = $commissionsLastMonth > 0 ? 
            (($commissionsThisMonth - $commissionsLastMonth) / $commissionsLastMonth) * 100 : 0;
        $clicksTrend = $clicksLastMonth > 0 ? 
            (($clicksThisMonth - $clicksLastMonth) / $clicksLastMonth) * 100 : 0;
        $signupsTrend = $signupsLastMonth > 0 ? 
            (($signupsThisMonth - $signupsLastMonth) / $signupsLastMonth) * 100 : 0;

        // Conversion rates
        $clickToSignup = $clicksThisMonth > 0 ? ($signupsThisMonth / $clicksThisMonth) * 100 : 0;
        $signupToVerified = $signupsThisMonth > 0 ? ($verifiedSignupsThisMonth / $signupsThisMonth) * 100 : 0;
        $verifiedToOrder = $verifiedSignupsThisMonth > 0 ? ($ordersThisMonth / $verifiedSignupsThisMonth) * 100 : 0;

        return [
            'clicksThisMonth' => $clicksThisMonth,
            'signupsThisMonth' => $signupsThisMonth,
            'verifiedSignupsThisMonth' => $verifiedSignupsThisMonth,
            'ordersThisMonth' => $ordersThisMonth,
            'commissionsThisMonth' => $commissionsThisMonth,
            'conversionRates' => [
                'clickToSignup' => round($clickToSignup, 2),
                'signupToVerified' => round($signupToVerified, 2),
                'verifiedToOrder' => round($verifiedToOrder, 2),
            ],
            'trends' => [
                'clicks' => round($clicksTrend, 2),
                'signups' => round($signupsTrend, 2),
                'commissions' => round($commissionsTrend, 2),
            ],
        ];
    }

    /**
     * Get referral statistics for affiliate
     */
    private function getReferralStats(User $user, array $filters): array
    {
        $affiliateProfile = $user->profilAffilie;
        $referralCode = $affiliateProfile?->referralCode;

        $totalReferrals = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)->count();
        $activeReferrals = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)
            ->where('verified', true)
            ->count();

        $clicksTotal = $referralCode ? ReferralClick::where('referral_code', $referralCode->code)->count() : 0;
        $thisMonth = Carbon::now()->startOfMonth();
        $clicksThisMonth = $referralCode ?
            ReferralClick::where('referral_code', $referralCode->code)
                ->where('created_at', '>=', $thisMonth)
                ->count() : 0;

        // Top performing products (based on orders from referrals)
        $topProducts = DB::table('commandes')
            ->join('commande_articles', 'commandes.id', '=', 'commande_articles.commande_id')
            ->join('produits', 'commande_articles.produit_id', '=', 'produits.id')
            ->join('referral_attributions', 'commandes.client_id', '=', 'referral_attributions.new_user_id')
            ->where('referral_attributions.referrer_affiliate_id', $affiliateProfile?->id)
            ->select([
                'produits.id',
                'produits.titre',
                DB::raw('COUNT(DISTINCT referral_attributions.id) as clicks'),
                DB::raw('COUNT(DISTINCT commandes.id) as conversions'),
                DB::raw('ROUND((COUNT(DISTINCT commandes.id) / COUNT(DISTINCT referral_attributions.id)) * 100, 2) as conversion_rate'),
                DB::raw('SUM(commissions_affilies.amount) as commissions'),
            ])
            ->leftJoin('commissions_affilies', 'commande_articles.id', '=', 'commissions_affilies.commande_article_id')
            ->groupBy('produits.id', 'produits.titre')
            ->orderBy('commissions', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->titre,
                    'clicks' => (int) $product->clicks,
                    'conversions' => (int) $product->conversions,
                    'conversionRate' => (float) $product->conversion_rate,
                    'commissions' => (float) ($product->commissions ?? 0),
                ];
            })
            ->toArray();

        // Recent clicks
        $recentClicks = $referralCode ?
            ReferralClick::where('referral_code', $referralCode->code)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($click) {
                    return [
                        'id' => $click->id,
                        'timestamp' => $click->created_at,
                        'source' => $click->source ?? 'direct',
                        'converted' => false, // Would need to check if this click led to a conversion
                        'orderId' => null,
                        'commission' => null,
                    ];
                })
                ->toArray() : [];

        return [
            'totalReferrals' => $totalReferrals,
            'activeReferrals' => $activeReferrals,
            'referralCode' => $referralCode?->code ?? '',
            'referralLink' => $referralCode ? config('app.url') . '/ref/' . $referralCode->code : '',
            'clicksTotal' => $clicksTotal,
            'clicksThisMonth' => $clicksThisMonth,
            'topPerformingProducts' => $topProducts,
            'recentClicks' => $recentClicks,
        ];
    }

    /**
     * Get commission statistics for affiliate
     */
    private function getCommissionStats(User $user, array $filters): array
    {
        $totalEarned = CommissionAffilie::where('user_id', $user->id)->sum('amount') ?? 0;
        $totalPaid = CommissionAffilie::where('user_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount') ?? 0;
        $pending = CommissionAffilie::where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount') ?? 0;
        $approved = CommissionAffilie::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('amount') ?? 0;

        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $thisMonthCommissions = CommissionAffilie::where('user_id', $user->id)
            ->where('created_at', '>=', $thisMonth)
            ->sum('amount') ?? 0;
        $lastMonthCommissions = CommissionAffilie::where('user_id', $user->id)
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->sum('amount') ?? 0;

        $growth = $lastMonthCommissions > 0 ?
            (($thisMonthCommissions - $lastMonthCommissions) / $lastMonthCommissions) * 100 : 0;

        $totalOrders = Commande::where('user_id', $user->id)->count();
        $averagePerOrder = $totalOrders > 0 ? $totalEarned / $totalOrders : 0;

        // Next payout info (if there are pending withdrawals)
        $nextPayout = Withdrawal::where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->first();

        return [
            'totalEarned' => $totalEarned,
            'totalPaid' => $totalPaid,
            'pending' => $pending,
            'approved' => $approved,
            'thisMonth' => $thisMonthCommissions,
            'lastMonth' => $lastMonthCommissions,
            'growth' => round($growth, 2),
            'averagePerOrder' => round($averagePerOrder, 2),
            'nextPayoutDate' => $nextPayout?->created_at,
            'nextPayoutAmount' => $nextPayout?->amount,
        ];
    }

    /**
     * Get order statistics for affiliate
     */
    private function getOrderStats(User $user, array $filters): array
    {
        $total = Commande::where('user_id', $user->id)->count();

        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $thisMonthOrders = Commande::where('user_id', $user->id)
            ->where('created_at', '>=', $thisMonth)
            ->count();
        $lastMonthOrders = Commande::where('user_id', $user->id)
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        $growth = $lastMonthOrders > 0 ?
            (($thisMonthOrders - $lastMonthOrders) / $lastMonthOrders) * 100 : 0;

        $averageValue = Commande::where('user_id', $user->id)->avg('total_ttc') ?? 0;

        // Status distribution
        $statusDistribution = Commande::where('user_id', $user->id)
            ->select('statut', DB::raw('count(*) as count'))
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        // Top products by orders
        $topProducts = DB::table('commandes')
            ->join('commande_articles', 'commandes.id', '=', 'commande_articles.commande_id')
            ->join('produits', 'commande_articles.produit_id', '=', 'produits.id')
            ->where('commandes.user_id', $user->id)
            ->select([
                'produits.id',
                'produits.titre',
                DB::raw('COUNT(commande_articles.id) as orders_count'),
                DB::raw('SUM(commande_articles.total_ligne) as revenue'),
                DB::raw('SUM(commissions_affilies.amount) as commission'),
            ])
            ->leftJoin('commissions_affilies', 'commande_articles.id', '=', 'commissions_affilies.commande_article_id')
            ->groupBy('produits.id', 'produits.titre')
            ->orderBy('orders_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->titre,
                    'ordersCount' => (int) $product->orders_count,
                    'revenue' => (float) ($product->revenue ?? 0),
                    'commission' => (float) ($product->commission ?? 0),
                ];
            })
            ->toArray();

        return [
            'total' => $total,
            'thisMonth' => $thisMonthOrders,
            'lastMonth' => $lastMonthOrders,
            'growth' => round($growth, 2),
            'averageValue' => round($averageValue, 2),
            'statusDistribution' => $statusDistribution,
            'topProducts' => $topProducts,
        ];
    }

    /**
     * Get points statistics for affiliate
     */
    private function getPointsStats(User $user, array $filters): array
    {
        $affiliateProfile = $user->profilAffilie;
        $current = $affiliateProfile ? $affiliateProfile->points : 0;

        // For now, we'll use simple calculations
        // In a real implementation, you'd have a points_transactions table
        $earned = $current; // Total points earned
        $dispensed = 0; // Points used/dispensed

        $thisMonth = Carbon::now()->startOfMonth();
        $earnedThisMonth = 0; // Would come from transactions this month
        $dispensedThisMonth = 0; // Would come from transactions this month

        // Mock points history - in real implementation, this would come from transactions table
        $history = collect([
            [
                'id' => 'signup_bonus',
                'type' => 'earned',
                'amount' => 100,
                'reason' => 'Signup bonus',
                'timestamp' => $user->created_at,
                'orderId' => null,
                'referralId' => null,
            ],
        ]);

        return [
            'current' => $current,
            'earned' => $earned,
            'dispensed' => $dispensed,
            'earnedThisMonth' => $earnedThisMonth,
            'dispensedThisMonth' => $dispensedThisMonth,
            'history' => $history->toArray(),
        ];
    }

    /**
     * Get affiliate rank
     */
    private function getAffiliateRank(User $user): int
    {
        $userCommissions = CommissionAffilie::where('user_id', $user->id)->sum('amount') ?? 0;

        $rank = User::role('affiliate')
            ->leftJoin('commissions_affilies', 'users.id', '=', 'commissions_affilies.user_id')
            ->select('users.id', DB::raw('COALESCE(SUM(commissions_affilies.amount), 0) as total_commissions'))
            ->groupBy('users.id')
            ->having('total_commissions', '>', $userCommissions)
            ->count();

        return $rank + 1;
    }

    /**
     * Chart data methods
     */
    private function getMySignupsChartData(User $user, string $period, array $filters): array
    {
        $affiliateProfile = $user->profilAffilie;
        $dateFormat = $this->getDateFormat($period);
        $dateRange = $this->getDateRange($period);

        $signupsData = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $labels = $this->generateDateLabels($period, $dateRange);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'My Signups',
                    'data' => array_map(fn($label) => $signupsData[$label] ?? 0, $labels),
                    'borderColor' => '#7367F0',
                    'backgroundColor' => 'rgba(115, 103, 240, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    private function getMyCommissionsChartData(User $user, string $period, array $filters): array
    {
        $dateFormat = $this->getDateFormat($period);
        $dateRange = $this->getDateRange($period);

        $commissionsData = CommissionAffilie::where('user_id', $user->id)
            ->whereBetween('created_at', $dateRange)
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COALESCE(SUM(amount), 0) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $labels = $this->generateDateLabels($period, $dateRange);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'My Commissions',
                    'data' => array_map(fn($label) => (float) ($commissionsData[$label] ?? 0), $labels),
                    'borderColor' => '#28C76F',
                    'backgroundColor' => 'rgba(40, 199, 111, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    private function getMyPointsChartData(User $user, string $period, array $filters): array
    {
        // Mock data for points over time
        // In real implementation, this would come from points_transactions table
        $labels = $this->generateDateLabels($period, $this->getDateRange($period));
        $data = array_fill(0, count($labels), 0);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Points Earned',
                    'data' => $data,
                    'borderColor' => '#FF9F43',
                    'backgroundColor' => 'rgba(255, 159, 67, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * Get my top products chart (pie/doughnut)
     */
    private function getMyTopProductsChart(User $user, array $filters): array
    {
        $query = DB::table('commandes')
            ->join('commande_articles', 'commandes.id', '=', 'commande_articles.commande_id')
            ->join('produits', 'commande_articles.produit_id', '=', 'produits.id')
            ->where('commandes.user_id', $user->id)
            ->select([
                'produits.titre as name',
                DB::raw('SUM(commissions_affilies.amount) as commissions'),
            ])
            ->leftJoin('commissions_affilies', 'commande_articles.id', '=', 'commissions_affilies.commande_article_id');

        // Apply date filters
        if (isset($filters['dateRange']['start']) && isset($filters['dateRange']['end'])) {
            $query->whereBetween('commandes.created_at', [
                $filters['dateRange']['start'] . ' 00:00:00',
                $filters['dateRange']['end'] . ' 23:59:59'
            ]);
        }

        $topProducts = $query
            ->groupBy('produits.id', 'produits.titre')
            ->orderBy('commissions', 'desc')
            ->limit(5)
            ->get();

        return [
            'items' => $topProducts->map(function ($product) {
                return [
                    'label' => $product->name,
                    'labelKey' => null,
                    'value' => (float) ($product->commissions ?? 0)
                ];
            })->toArray()
        ];
    }

    private function getReferralPerformanceChart(User $user, array $filters): array
    {
        $affiliateProfile = $user->profilAffilie;
        $referralCode = $affiliateProfile?->referralCode;

        $clicks = $referralCode ? ReferralClick::where('referral_code', $referralCode->code)->count() : 0;
        $signups = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)->count();
        $verified = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)
            ->where('verified', true)
            ->count();

        return [
            'labels' => ['Clicks', 'Signups', 'Verified'],
            'datasets' => [
                [
                    'label' => 'Referral Funnel',
                    'data' => [$clicks, $signups, $verified],
                    'backgroundColor' => ['#7367F0', '#FF9F43', '#28C76F'],
                ],
            ],
        ];
    }

    /**
     * Get my recent orders table data
     */
    private function getMyRecentOrders(User $user, array $filters): array
    {
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        $query = Commande::where('user_id', $user->id)
            ->with(['client:id,nom_complet', 'articles.produit:id,titre'])
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $orders = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($order) {
                $productTitle = $order->articles->first()?->produit?->titre ?? 'Unknown Product';

                return [
                    'product' => $productTitle,
                    'amount' => (float) $order->total_ttc,
                    'status' => $order->statut,
                    'date' => $order->created_at->toISOString(),
                ];
            });

        return [
            'rows' => $orders->toArray(),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total
            ]
        ];
    }

    /**
     * Get my recent payments table data
     */
    private function getMyRecentPayments(User $user, array $filters): array
    {
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        $query = Withdrawal::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $payments = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($payment) {
                return [
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status,
                    'date' => $payment->created_at->toISOString(),
                ];
            });

        return [
            'rows' => $payments->toArray(),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total
            ]
        ];
    }

    /**
     * Get my active referrals table data
     */
    private function getMyActiveReferrals(User $user, array $filters): array
    {
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;
        $affiliateProfile = $user->profilAffilie;

        $query = ReferralAttribution::where('referrer_affiliate_id', $affiliateProfile?->id)
            ->with('newUser:id,nom_complet,email,created_at')
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $referrals = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($attribution) {
                $newUser = $attribution->newUser;
                return [
                    'name' => $newUser->nom_complet ?? 'Unknown',
                    'email' => $newUser->email ?? 'Unknown',
                    'signup_date' => $attribution->created_at->toISOString(),
                    'status' => $attribution->verified ? 'verified' : 'pending',
                ];
            });

        return [
            'rows' => $referrals->toArray(),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total
            ]
        ];
    }

    private function getReferralClicks(User $user, array $filters): array
    {
        $affiliateProfile = $user->profilAffilie;
        $referralCode = $affiliateProfile?->referralCode;

        if (!$referralCode) {
            return [];
        }

        $clicks = ReferralClick::where('referral_code', $referralCode->code)
            ->orderBy('created_at', 'desc')
            ->limit($filters['per_page'])
            ->get()
            ->map(function ($click) {
                return [
                    'id' => $click->id,
                    'timestamp' => $click->created_at,
                    'source' => $click->source ?? 'direct',
                    'converted' => false, // Would need to check if this led to a signup/order
                    'orderId' => null,
                    'commission' => null,
                ];
            })
            ->toArray();

        return $clicks;
    }

    /**
     * Helper methods for date formatting
     */
    private function getDateFormat(string $period): string
    {
        return match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d',
        };
    }

    private function getDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'day' => [$now->subDays(30), $now],
            'week' => [$now->subWeeks(12), $now],
            'month' => [$now->subMonths(12), $now],
            'year' => [$now->subYears(5), $now],
            default => [$now->subDays(30), $now],
        };
    }

    private function generateDateLabels(string $period, array $dateRange): array
    {
        $labels = [];
        $start = Carbon::parse($dateRange[0]);
        $end = Carbon::parse($dateRange[1]);

        switch ($period) {
            case 'day':
                while ($start->lte($end)) {
                    $labels[] = $start->format('Y-m-d');
                    $start->addDay();
                }
                break;
            case 'week':
                while ($start->lte($end)) {
                    $labels[] = $start->format('Y-W');
                    $start->addWeek();
                }
                break;
            case 'month':
                while ($start->lte($end)) {
                    $labels[] = $start->format('Y-m');
                    $start->addMonth();
                }
                break;
            case 'year':
                while ($start->lte($end)) {
                    $labels[] = $start->format('Y');
                    $start->addYear();
                }
                break;
        }

        return $labels;
    }
}
