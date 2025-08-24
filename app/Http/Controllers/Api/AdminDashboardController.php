<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ProfilAffilie;
use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Models\Ticket;
use App\Models\ReferralAttribution;
use App\Models\ReferralClick;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Get comprehensive admin dashboard statistics
     */
    public function getStats(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }



        $filters = $this->parseFilters($request);
        $cacheKey = 'admin_dashboard_stats_' . md5(serialize($filters));

        $stats = Cache::remember($cacheKey, 300, function () use ($filters) {
            return [
                'overview' => $this->getOverviewStats($filters),
                'affiliates' => $this->getAffiliateStats($filters),
                'orders' => $this->getOrderStats($filters),
                'revenue' => $this->getRevenueStats($filters),
                'commissions' => $this->getCommissionStats($filters),
                'payouts' => $this->getPayoutStats($filters),
                'points' => $this->getPointsStats($filters),
                'tickets' => $this->getTicketStats($filters),
            ];
        });



        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get chart data for admin dashboard
     */
    public function getChartData(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $period = $request->get('period', 'month'); // day, week, month, year

        $chartData = [
            'signups_over_time' => $this->getSignupsChartData($period, $filters),
            'revenue_over_time' => $this->getRevenueChartData($period, $filters),
            'commissions_over_time' => $this->getCommissionsChartData($period, $filters),
            'top_affiliates_commissions' => $this->getTopAffiliatesChart($filters),
            'top_affiliates_signups' => $this->getTopAffiliatesSignupsChart($filters),
            'orders_by_status' => $this->getOrdersByStatusChart($filters),
            'conversion_funnel' => $this->getConversionFunnelData($filters),
        ];

        return response()->json([
            'success' => true,
            'data' => $chartData,
        ]);
    }

    /**
     * Get table data for admin dashboard
     */
    public function getTableData(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $type = $request->get('type');
        $filters = $this->parseFilters($request);

        switch ($type) {
            case 'recent_affiliates':
                $data = $this->getRecentAffiliates($filters);
                break;
            case 'recent_payouts':
                $data = $this->getRecentPayouts($filters);
                break;
            case 'recent_tickets':
                $data = $this->getRecentTickets($filters);
                break;
            case 'recent_activities':
                $data = $this->getRecentActivities($filters);
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
     * Parse request filters
     */
    private function parseFilters(Request $request): array
    {
        $dateStart = $request->get('date_start');
        $dateEnd = $request->get('date_end');
        if (!$dateStart && !$dateEnd && $request->has('dateRange')) {
            try {
                $range = $request->get('dateRange');
                if (is_string($range)) {
                    $decoded = json_decode($range, true);
                } else {
                    $decoded = $range;
                }
                if (is_array($decoded)) {
                    $dateStart = $decoded['start'] ?? null;
                    $dateEnd = $decoded['end'] ?? null;
                }
            } catch (\Throwable $e) {
                // ignore malformed
            }
        }
        $dateStart = $dateStart ?: Carbon::now()->startOfMonth()->toDateString();
        $dateEnd = $dateEnd ?: Carbon::now()->toDateString();

        return [
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'affiliate_id' => $request->get('affiliate_id'),
            'status' => $request->get('status'),
            'country' => $request->get('country'),
            'city' => $request->get('city'),
            'page' => (int) $request->get('page', 1),
            'per_page' => min((int) $request->get('per_page', 15), 100),
        ];
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats(array $filters): array
    {
        $now = Carbon::now();
        $thisMonth = $now->startOfMonth();
        $last24h = $now->subDay();
        $last7d = $now->subDays(7);

        // Total affiliates
        $totalAffiliates = User::role('affiliate')->count();

        // Signups (new affiliate registrations)
        $totalSignups = User::role('affiliate')->count();
        $signupsLast24h = User::role('affiliate')
            ->where('created_at', '>=', $last24h)
            ->count();
        $signupsLast7d = User::role('affiliate')
            ->where('created_at', '>=', $last7d)
            ->count();
        $signupsMTD = User::role('affiliate')
            ->where('created_at', '>=', $thisMonth)
            ->count();

        // Verified signups
        $verifiedSignups = User::role('affiliate')
            ->where('email_verifie', true)
            ->count();
        $verificationRate = $totalSignups > 0 ? ($verifiedSignups / $totalSignups) * 100 : 0;

        // Orders and revenue
        $totalOrders = Commande::count();
        $totalRevenue = Commande::sum('total_ttc') ?? 0;

        // Commissions
        $totalCommissions = CommissionAffilie::sum('amount') ?? 0;

        // Pending payouts
        $pendingPayouts = Withdrawal::where('status', 'pending')->sum('amount') ?? 0;

        return [
            'totalAffiliates' => $totalAffiliates,
            'totalSignups' => $totalSignups,
            'signupsLast24h' => $signupsLast24h,
            'signupsLast7d' => $signupsLast7d,
            'signupsMTD' => $signupsMTD,
            'verifiedSignups' => $verifiedSignups,
            'verificationRate' => round($verificationRate, 2),
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'totalCommissions' => $totalCommissions,
            'pendingPayouts' => $pendingPayouts,
        ];
    }

    /**
     * Get affiliate statistics
     */
    private function getAffiliateStats(array $filters): array
    {
        $total = User::role('affiliate')->count();
        $active = User::role('affiliate')->where('statut', 'actif')->count();
        $suspended = User::role('affiliate')->where('statut', 'suspendu')->count();

        $thisMonth = Carbon::now()->startOfMonth();
        $newThisMonth = User::role('affiliate')
            ->where('created_at', '>=', $thisMonth)
            ->count();

        // Top performers by commissions
        $topPerformers = User::role('affiliate')
            ->select([
                'users.id',
                'users.nom_complet as name',
                'users.email',
                'users.created_at as joinedAt',
                DB::raw('COALESCE(SUM(commissions_affilies.amount), 0) as totalCommissions'),
                DB::raw('COUNT(DISTINCT commandes.id) as ordersCount'),
                DB::raw('COUNT(DISTINCT referral_attributions.id) as verifiedSignups'),
            ])
            ->leftJoin('commissions_affilies', 'users.id', '=', 'commissions_affilies.user_id')
            ->leftJoin('commandes', 'users.id', '=', 'commandes.user_id')
            ->leftJoin('referral_attributions', function ($join) {
                $join->on('users.id', '=', 'referral_attributions.referrer_affiliate_id')
                     ->where('referral_attributions.verified', true);
            })
            ->groupBy('users.id', 'users.nom_complet', 'users.email', 'users.created_at')
            ->orderBy('totalCommissions', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($affiliate) {
                return [
                    'id' => $affiliate->id,
                    'name' => $affiliate->name,
                    'email' => $affiliate->email,
                    'totalCommissions' => (float) $affiliate->totalCommissions,
                    'ordersCount' => (int) $affiliate->ordersCount,
                    'verifiedSignups' => (int) $affiliate->verifiedSignups,
                    'conversionRate' => $affiliate->verifiedSignups > 0 && $affiliate->ordersCount > 0
                        ? round(($affiliate->ordersCount / $affiliate->verifiedSignups) * 100, 2)
                        : 0,
                    'joinedAt' => $affiliate->joinedAt,
                ];
            })
            ->toArray();

        // Status distribution
        $statusDistribution = User::role('affiliate')
            ->select('statut', DB::raw('count(*) as count'))
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'suspended' => $suspended,
            'newThisMonth' => $newThisMonth,
            'topPerformers' => $topPerformers,
            'statusDistribution' => $statusDistribution,
        ];
    }

    /**
     * Get order statistics
     */
    private function getOrderStats(array $filters): array
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $total = Commande::count();
        $thisMonthCount = Commande::where('created_at', '>=', $thisMonth)->count();
        $lastMonthCount = Commande::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->count();

        $growth = $lastMonthCount > 0 ? (($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100 : 0;

        // Status distribution
        $statusDistribution = Commande::select('statut', DB::raw('count(*) as count'))
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        // Average order value
        $averageOrderValue = Commande::avg('total_ttc') ?? 0;

        return [
            'total' => $total,
            'thisMonth' => $thisMonthCount,
            'lastMonth' => $lastMonthCount,
            'growth' => round($growth, 2),
            'statusDistribution' => $statusDistribution,
            'averageOrderValue' => round($averageOrderValue, 2),
        ];
    }

    /**
     * Get revenue statistics
     */
    private function getRevenueStats(array $filters): array
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $total = Commande::sum('total_ttc') ?? 0;
        $thisMonthRevenue = Commande::where('created_at', '>=', $thisMonth)->sum('total_ttc') ?? 0;
        $lastMonthRevenue = Commande::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->sum('total_ttc') ?? 0;

        $growth = $lastMonthRevenue > 0 ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

        $averagePerOrder = Commande::avg('total_ttc') ?? 0;
        $affiliateCount = User::role('affiliate')->count();
        $averagePerAffiliate = $affiliateCount > 0 ? $total / $affiliateCount : 0;

        return [
            'total' => $total,
            'thisMonth' => $thisMonthRevenue,
            'lastMonth' => $lastMonthRevenue,
            'growth' => round($growth, 2),
            'averagePerOrder' => round($averagePerOrder, 2),
            'averagePerAffiliate' => round($averagePerAffiliate, 2),
        ];
    }

    /**
     * Get commission statistics
     */
    private function getCommissionStats(array $filters): array
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $total = CommissionAffilie::sum('amount') ?? 0;
        $thisMonthCommissions = CommissionAffilie::where('created_at', '>=', $thisMonth)->sum('amount') ?? 0;
        $lastMonthCommissions = CommissionAffilie::whereBetween('created_at', [$lastMonth, $lastMonthEnd])->sum('amount') ?? 0;

        $growth = $lastMonthCommissions > 0 ? (($thisMonthCommissions - $lastMonthCommissions) / $lastMonthCommissions) * 100 : 0;

        $pending = CommissionAffilie::where('status', 'pending')->sum('amount') ?? 0;
        $approved = CommissionAffilie::where('status', 'approved')->sum('amount') ?? 0;
        $paid = CommissionAffilie::where('status', 'paid')->sum('amount') ?? 0;

        // Calculate average commission rate
        $totalOrderValue = Commande::sum('total_ttc') ?? 1;
        $averageRate = $totalOrderValue > 0 ? ($total / $totalOrderValue) * 100 : 0;

        return [
            'total' => $total,
            'thisMonth' => $thisMonthCommissions,
            'lastMonth' => $lastMonthCommissions,
            'growth' => round($growth, 2),
            'pending' => $pending,
            'approved' => $approved,
            'paid' => $paid,
            'averageRate' => round($averageRate, 2),
        ];
    }

    /**
     * Get payout statistics
     */
    private function getPayoutStats(array $filters): array
    {
        $pending = Withdrawal::where('status', 'pending')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $approved = Withdrawal::where('status', 'approved')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $paid = Withdrawal::where('status', 'paid')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $rejected = Withdrawal::where('status', 'rejected')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->first();

        // Calculate average processing time (from pending to paid)
        $averageProcessingTime = Withdrawal::whereNotNull('paid_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, paid_at)) as avg_hours')
            ->value('avg_hours') ?? 0;

        return [
            'pending' => [
                'count' => $pending->count,
                'amount' => $pending->amount,
            ],
            'approved' => [
                'count' => $approved->count,
                'amount' => $approved->amount,
            ],
            'paid' => [
                'count' => $paid->count,
                'amount' => $paid->amount,
            ],
            'rejected' => [
                'count' => $rejected->count,
                'amount' => $rejected->amount,
            ],
            'averageProcessingTime' => round($averageProcessingTime, 1),
        ];
    }

    /**
     * Get points statistics
     */
    private function getPointsStats(array $filters): array
    {
        $thisMonth = Carbon::now()->startOfMonth();

        // For now, we'll use the points from profils_affilies table
        // In a real implementation, you'd have a points_transactions table
        $totalEarned = ProfilAffilie::sum('points') ?? 0;
        $totalDispensed = 0; // Would come from points transactions
        $earnedThisMonth = ProfilAffilie::where('updated_at', '>=', $thisMonth)->sum('points') ?? 0;
        $dispensedThisMonth = 0; // Would come from points transactions

        $balance = $totalEarned - $totalDispensed;

        // Top points earners
        $topEarners = ProfilAffilie::with('utilisateur:id,nom_complet')
            ->orderBy('points', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($profile) {
                return [
                    'id' => $profile->utilisateur->id,
                    'name' => $profile->utilisateur->nom_complet,
                    'pointsEarned' => $profile->points,
                    'pointsDispensed' => 0, // Would come from transactions
                    'balance' => $profile->points,
                ];
            })
            ->toArray();

        return [
            'totalEarned' => $totalEarned,
            'totalDispensed' => $totalDispensed,
            'earnedThisMonth' => $earnedThisMonth,
            'dispensedThisMonth' => $dispensedThisMonth,
            'balance' => $balance,
            'topEarners' => $topEarners,
        ];
    }

    /**
     * Get ticket statistics
     */
    private function getTicketStats(array $filters): array
    {
        $total = Ticket::count();
        $open = Ticket::where('status', 'open')->count();
        $inProgress = Ticket::where('status', 'in_progress')->count();
        $resolved = Ticket::where('status', 'resolved')->count();

        // Average response time (first response)
        $averageResponseTime = Ticket::whereNotNull('first_response_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at)) as avg_hours')
            ->value('avg_hours') ?? 0;

        // Average resolution time
        $averageResolutionTime = Ticket::whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->value('avg_hours') ?? 0;

        // Priority distribution
        $priorityDistribution = Ticket::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        return [
            'total' => $total,
            'open' => $open,
            'inProgress' => $inProgress,
            'resolved' => $resolved,
            'averageResponseTime' => round($averageResponseTime, 1),
            'averageResolutionTime' => round($averageResolutionTime, 1),
            'priorityDistribution' => $priorityDistribution,
        ];
    }

    /**
     * Get signups chart data
     */
    private function getSignupsChartData(string $period, array $filters): array
    {
        $dateFormat = $this->getDateFormat($period);
        $dateRange = $this->getDateRange($period);

        $signupsData = User::role('affiliate')
            ->whereBetween('created_at', $dateRange)
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $verifiedSignupsData = User::role('affiliate')
            ->where('email_verifie', true)
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
                    'label' => 'Total Signups',
                    'data' => array_map(fn($label) => $signupsData[$label] ?? 0, $labels),
                    'borderColor' => '#7367F0',
                    'backgroundColor' => 'rgba(115, 103, 240, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Verified Signups',
                    'data' => array_map(fn($label) => $verifiedSignupsData[$label] ?? 0, $labels),
                    'borderColor' => '#28C76F',
                    'backgroundColor' => 'rgba(40, 199, 111, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * Get revenue chart data
     */
    private function getRevenueChartData(string $period, array $filters): array
    {
        $dateFormat = $this->getDateFormat($period);
        $dateRange = $this->getDateRange($period);

        $revenueData = Commande::whereBetween('created_at', $dateRange)
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COALESCE(SUM(total_ttc), 0) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $commissionsData = CommissionAffilie::whereBetween('created_at', $dateRange)
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
                    'label' => 'Revenue',
                    'data' => array_map(fn($label) => (float) ($revenueData[$label] ?? 0), $labels),
                    'borderColor' => '#FF9F43',
                    'backgroundColor' => 'rgba(255, 159, 67, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Commissions',
                    'data' => array_map(fn($label) => (float) ($commissionsData[$label] ?? 0), $labels),
                    'borderColor' => '#EA5455',
                    'backgroundColor' => 'rgba(234, 84, 85, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * Get commissions chart data
     */
    private function getCommissionsChartData(string $period, array $filters): array
    {
        $dateFormat = $this->getDateFormat($period);
        $dateRange = $this->getDateRange($period);

        $commissionsData = CommissionAffilie::whereBetween('created_at', $dateRange)
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
                    'label' => 'Commissions',
                    'data' => array_map(fn($label) => (float) ($commissionsData[$label] ?? 0), $labels),
                    'borderColor' => '#7367F0',
                    'backgroundColor' => 'rgba(115, 103, 240, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * Get top affiliates by commissions chart
     */
    private function getTopAffiliatesChart(array $filters): array
    {
        $topAffiliates = User::role('affiliate')
            ->select([
                'users.nom_complet as name',
                DB::raw('COALESCE(SUM(commissions_affilies.amount), 0) as total_commissions'),
            ])
            ->leftJoin('commissions_affilies', 'users.id', '=', 'commissions_affilies.user_id')
            ->groupBy('users.id', 'users.nom_complet')
            ->orderBy('total_commissions', 'desc')
            ->limit(10)
            ->get();

        return [
            'labels' => $topAffiliates->pluck('name')->toArray(),
            'datasets' => [
                [
                    'label' => 'Total Commissions',
                    'data' => $topAffiliates->pluck('total_commissions')->map(fn($val) => (float) $val)->toArray(),
                    'backgroundColor' => [
                        '#7367F0', '#28C76F', '#FF9F43', '#EA5455', '#00CFE8',
                        '#9F87FF', '#FFC107', '#FF6B6B', '#4ECDC4', '#45B7D1',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get top affiliates by signups chart
     */
    private function getTopAffiliatesSignupsChart(array $filters): array
    {
        $topAffiliates = User::role('affiliate')
            ->select([
                'users.nom_complet as name',
                DB::raw('COUNT(referral_attributions.id) as verified_signups'),
            ])
            ->leftJoin('profils_affilies', 'users.id', '=', 'profils_affilies.utilisateur_id')
            ->leftJoin('referral_attributions', function ($join) {
                $join->on('profils_affilies.id', '=', 'referral_attributions.referrer_affiliate_id')
                     ->where('referral_attributions.verified', true);
            })
            ->groupBy('users.id', 'users.nom_complet')
            ->orderBy('verified_signups', 'desc')
            ->limit(10)
            ->get();

        return [
            'labels' => $topAffiliates->pluck('name')->toArray(),
            'datasets' => [
                [
                    'label' => 'Verified Signups',
                    'data' => $topAffiliates->pluck('verified_signups')->map(fn($val) => (int) $val)->toArray(),
                    'backgroundColor' => [
                        '#28C76F', '#7367F0', '#FF9F43', '#EA5455', '#00CFE8',
                        '#9F87FF', '#FFC107', '#FF6B6B', '#4ECDC4', '#45B7D1',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get orders by status chart
     */
    private function getOrdersByStatusChart(array $filters): array
    {
        $ordersByStatus = Commande::select('statut', DB::raw('count(*) as count'))
            ->groupBy('statut')
            ->get();

        return [
            'labels' => $ordersByStatus->pluck('statut')->toArray(),
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $ordersByStatus->pluck('count')->map(fn($val) => (int) $val)->toArray(),
                    'backgroundColor' => [
                        '#7367F0', '#28C76F', '#FF9F43', '#EA5455', '#00CFE8',
                        '#9F87FF', '#FFC107', '#FF6B6B', '#4ECDC4', '#45B7D1',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get conversion funnel data
     */
    private function getConversionFunnelData(array $filters): array
    {
        $clicks = ReferralClick::count();
        $signups = User::role('affiliate')->count();
        $verified = User::role('affiliate')->where('email_verifie', true)->count();
        $orders = Commande::count();

        $clickToSignup = $clicks > 0 ? ($signups / $clicks) * 100 : 0;
        $signupToVerified = $signups > 0 ? ($verified / $signups) * 100 : 0;
        $verifiedToOrder = $verified > 0 ? ($orders / $verified) * 100 : 0;

        return [
            'clicks' => $clicks,
            'signups' => $signups,
            'verified' => $verified,
            'orders' => $orders,
            'rates' => [
                'clickToSignup' => round($clickToSignup, 2),
                'signupToVerified' => round($signupToVerified, 2),
                'verifiedToOrder' => round($verifiedToOrder, 2),
            ],
        ];
    }

    /**
     * Get recent affiliates
     */
    private function getRecentAffiliates(array $filters): array
    {
        $affiliates = User::role('affiliate')
            ->select([
                'users.id',
                'users.nom_complet as name',
                'users.email',
                'users.created_at as joinedAt',
                'users.statut as status',
                'users.updated_at as lastActivity',
                DB::raw('COALESCE(SUM(commissions_affilies.amount), 0) as totalCommissions'),
                DB::raw('COUNT(DISTINCT commandes.id) as ordersCount'),
                DB::raw('COUNT(DISTINCT referral_attributions.id) as verifiedSignups'),
            ])
            ->leftJoin('commissions_affilies', 'users.id', '=', 'commissions_affilies.user_id')
            ->leftJoin('commandes', 'users.id', '=', 'commandes.user_id')
            ->leftJoin('profils_affilies', 'users.id', '=', 'profils_affilies.utilisateur_id')
            ->leftJoin('referral_attributions', function ($join) {
                $join->on('profils_affilies.id', '=', 'referral_attributions.referrer_affiliate_id')
                     ->where('referral_attributions.verified', true);
            })
            ->groupBy('users.id', 'users.nom_complet', 'users.email', 'users.created_at', 'users.statut', 'users.updated_at')
            ->orderBy('users.created_at', 'desc')
            ->limit($filters['per_page'])
            ->get()
            ->map(function ($affiliate) {
                return [
                    'id' => $affiliate->id,
                    'name' => $affiliate->name,
                    'email' => $affiliate->email,
                    'joinedAt' => $affiliate->joinedAt,
                    'status' => $affiliate->status,
                    'totalCommissions' => (float) $affiliate->totalCommissions,
                    'ordersCount' => (int) $affiliate->ordersCount,
                    'verifiedSignups' => (int) $affiliate->verifiedSignups,
                    'lastActivity' => $affiliate->lastActivity,
                ];
            })
            ->toArray();

        return $affiliates;
    }

    /**
     * Get recent payouts
     */
    private function getRecentPayouts(array $filters): array
    {
        $payouts = Withdrawal::with('user:id,nom_complet')
            ->select([
                'id',
                'user_id',
                'amount',
                'status',
                'method',
                'created_at as requestedAt',
                'paid_at as processedAt',
                'notes',
            ])
            ->orderBy('created_at', 'desc')
            ->limit($filters['per_page'])
            ->get()
            ->map(function ($payout) {
                return [
                    'id' => $payout->id,
                    'affiliateId' => $payout->user_id,
                    'affiliateName' => $payout->user->nom_complet ?? 'Unknown',
                    'amount' => (float) $payout->amount,
                    'status' => $payout->status,
                    'method' => $payout->method,
                    'requestedAt' => $payout->requestedAt,
                    'processedAt' => $payout->processedAt,
                    'notes' => $payout->notes,
                ];
            })
            ->toArray();

        return $payouts; // now an array
    }

    /**
     * Get recent tickets
     */
    private function getRecentTickets(array $filters): array
    {
        $tickets = Ticket::with(['requester:id,nom_complet', 'assignee:id,nom_complet'])
            ->select([
                'id',
                'subject',
                'priority',
                'status',
                'requester_id',
                'assignee_id',
                'created_at',
                'last_activity_at',
            ])
            ->orderBy('created_at', 'desc')
            ->limit($filters['per_page'])
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'subject' => $ticket->subject,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'requesterName' => $ticket->requester->nom_complet ?? 'Unknown',
                    'assigneeName' => $ticket->assignee->nom_complet ?? null,
                    'createdAt' => $ticket->created_at,
                    'lastActivity' => $ticket->last_activity_at,
                ];
            })
            ->toArray();

        return $tickets; // now an array
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities(array $filters): array
    {
        $activities = collect();

        // Recent signups
        $recentSignups = User::role('affiliate')
            ->with('profilAffilie')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => 'signup_' . $user->id,
                    'type' => 'signup',
                    'description' => 'New affiliate signup',
                    'affiliateName' => $user->nom_complet,
                    'timestamp' => $user->created_at,
                    'metadata' => ['user_id' => $user->id],
                ];
            })
            ->toArray();

        // Recent orders
        $recentOrders = Commande::with('affiliate:id,nom_complet')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => 'order_' . $order->id,
                    'type' => 'order',
                    'description' => 'New order placed',
                    'affiliateName' => $order->affiliate->nom_complet ?? 'Unknown',
                    'amount' => (float) $order->total_ttc,
                    'timestamp' => $order->created_at,
                    'metadata' => ['order_id' => $order->id],
                ];
            })
            ->toArray();

        $activities = collect(array_merge($recentSignups, $recentOrders));

        return $activities->sortByDesc('timestamp')->take($filters['per_page'])->values()->toArray();
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
