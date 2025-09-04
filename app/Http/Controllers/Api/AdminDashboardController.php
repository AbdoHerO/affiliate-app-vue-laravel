<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ProfilAffilie;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Models\Ticket;
use App\Models\ReferralAttribution;
use App\Models\ReferralClick;
use App\Models\Produit;
use App\Models\Categorie;
use App\Models\Boutique;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Get admin dashboard KPI cards
     */
    public function getStats(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $cacheKey = 'admin_dashboard_cards_' . md5(serialize($filters));

        $cards = Cache::remember($cacheKey, 300, function () use ($filters) {
            return $this->getKpiCards($filters);
        });

        return response()->json([
            'success' => true,
            'data' => ['cards' => $cards],
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

        $chartType = $request->get('type');
        $filters = $this->parseFilters($request);
        $cacheKey = 'admin_dashboard_chart_' . $chartType . '_' . md5(serialize($filters));

        $chartData = Cache::remember($cacheKey, 300, function () use ($chartType, $filters) {
            switch ($chartType) {
                case 'orders_by_period':
                    return $this->getOrdersByPeriodChart($filters);
                case 'monthly_revenue':
                    return $this->getMonthlyRevenueChart($filters);
                case 'top_affiliates':
                    return $this->getTopAffiliatesChart($filters);
                case 'top_products':
                    return $this->getTopProductsChart($filters);
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
            case 'recent_payments':
                $data = $this->getRecentPayments($filters);
                break;
            case 'monthly_paid_commissions':
                $data = $this->getMonthlyPaidCommissions($filters);
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
            'period' => $request->get('period', 'month'), // Add period parameter
            'affiliate_id' => $request->get('affiliate_id'),
            'status' => $request->get('status'),
            'country' => $request->get('country'),
            'city' => $request->get('city'),
            'page' => (int) $request->get('page', 1),
            'per_page' => min((int) $request->get('per_page', 15), 100),
        ];
    }

    /**
     * Get KPI cards data (6 indicators) - Unified response format
     */
    private function getKpiCards(array $filters): array
    {
        return [
            [
                'key' => 'active_affiliates',
                'labelKey' => 'dashboard.admin.cards.active_affiliates',
                'value' => User::role('affiliate')->where('statut', 'actif')->count()
            ],
            [
                'key' => 'total_orders',
                'labelKey' => 'dashboard.admin.cards.total_orders',
                'value' => Commande::count()
            ],
            [
                'key' => 'total_revenue',
                'labelKey' => 'dashboard.admin.cards.total_revenue',
                'value' => (float) (Commande::sum('total_ttc') ?? 0)
            ],
            [
                'key' => 'total_commissions',
                'labelKey' => 'dashboard.admin.cards.total_commissions',
                'value' => (float) (CommissionAffilie::sum('amount') ?? 0)
            ],
            [
                'key' => 'pending_payments',
                'labelKey' => 'dashboard.admin.cards.pending_payments',
                'value' => (float) (Withdrawal::where('status', 'pending')->sum('amount') ?? 0)
            ],
            [
                'key' => 'pending_tickets',
                'labelKey' => 'dashboard.admin.cards.pending_tickets',
                'value' => Ticket::whereIn('status', ['open', 'pending', 'waiting_user', 'waiting_third_party'])->count()
            ]
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
     * Get orders by period chart data (monthly bars)
     */
    private function getOrdersByPeriodChart(array $filters): array
    {
        $period = $filters['period'] ?? 'month';
        $dateStart = Carbon::parse($filters['date_start']);
        $dateEnd = Carbon::parse($filters['date_end']);

        switch ($period) {
            case 'year':
                // Group by year
                $dateFormat = '%Y';
                $categories = collect();
                $current = $dateStart->copy()->startOfYear();
                while ($current <= $dateEnd) {
                    $categories->push($current->format('Y'));
                    $current->addYear();
                }
                break;

            case 'quarter':
                // Group by quarter
                $dateFormat = '%Y-Q%u';
                $categories = collect();
                $current = $dateStart->copy()->startOfQuarter();
                while ($current <= $dateEnd) {
                    $quarter = ceil($current->month / 3);
                    $categories->push($current->year . '-Q' . $quarter);
                    $current->addQuarter();
                }
                break;

            case 'month':
            default:
                // Group by month
                $dateFormat = '%Y-%m';
                $categories = collect();
                $current = $dateStart->copy()->startOfMonth();
                while ($current <= $dateEnd) {
                    $categories->push($current->format('Y-m'));
                    $current->addMonth();
                }
                break;
        }

        // Build the SQL query based on period
        $query = Commande::whereBetween('created_at', [$dateStart, $dateEnd]);
        
        if ($period === 'year') {
            $ordersData = $query->selectRaw("YEAR(created_at) as period_key, COUNT(*) as count")
                ->groupBy('period_key')
                ->orderBy('period_key')
                ->pluck('count', 'period_key')
                ->toArray();
        } elseif ($period === 'quarter') {
            $ordersData = $query->selectRaw("CONCAT(YEAR(created_at), '-Q', QUARTER(created_at)) as period_key, COUNT(*) as count")
                ->groupBy('period_key')
                ->orderBy('period_key')
                ->pluck('count', 'period_key')
                ->toArray();
        } else {
            $ordersData = $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period_key, COUNT(*) as count")
                ->groupBy('period_key')
                ->orderBy('period_key')
                ->pluck('count', 'period_key')
                ->toArray();
        }

        return [
            'series' => [
                [
                    'nameKey' => 'dashboard.admin.charts.orders_by_period.series.orders',
                    'data' => $categories->map(fn($cat) => (int) ($ordersData[$cat] ?? 0))->values()->toArray(),
                    'categories' => $categories->toArray(),
                    'period' => $period
                ]
            ]
        ];
    }

    /**
     * Get revenue chart data (line or bars) - respects period parameter
     */
    private function getMonthlyRevenueChart(array $filters): array
    {
        $period = $filters['period'] ?? 'month';
        $dateStart = Carbon::parse($filters['date_start']);
        $dateEnd = Carbon::parse($filters['date_end']);

        switch ($period) {
            case 'year':
                // Group by year
                $categories = collect();
                $current = $dateStart->copy()->startOfYear();
                while ($current <= $dateEnd) {
                    $categories->push($current->format('Y'));
                    $current->addYear();
                }
                break;

            case 'quarter':
                // Group by quarter
                $categories = collect();
                $current = $dateStart->copy()->startOfQuarter();
                while ($current <= $dateEnd) {
                    $quarter = ceil($current->month / 3);
                    $categories->push($current->year . '-Q' . $quarter);
                    $current->addQuarter();
                }
                break;

            case 'month':
            default:
                // Group by month
                $categories = collect();
                $current = $dateStart->copy()->startOfMonth();
                while ($current <= $dateEnd) {
                    $categories->push($current->format('Y-m'));
                    $current->addMonth();
                }
                break;
        }

        // Build the SQL query based on period
        $query = Commande::whereBetween('created_at', [$dateStart, $dateEnd]);
        
        if ($period === 'year') {
            $revenueData = $query->selectRaw("YEAR(created_at) as period_key, COALESCE(SUM(total_ttc), 0) as total")
                ->groupBy('period_key')
                ->orderBy('period_key')
                ->pluck('total', 'period_key')
                ->toArray();
        } elseif ($period === 'quarter') {
            $revenueData = $query->selectRaw("CONCAT(YEAR(created_at), '-Q', QUARTER(created_at)) as period_key, COALESCE(SUM(total_ttc), 0) as total")
                ->groupBy('period_key')
                ->orderBy('period_key')
                ->pluck('total', 'period_key')
                ->toArray();
        } else {
            $revenueData = $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period_key, COALESCE(SUM(total_ttc), 0) as total")
                ->groupBy('period_key')
                ->orderBy('period_key')
                ->pluck('total', 'period_key')
                ->toArray();
        }

        return [
            'series' => [
                [
                    'nameKey' => 'dashboard.admin.charts.monthly_revenue.series.revenue',
                    'data' => $categories->map(fn($cat) => (float) ($revenueData[$cat] ?? 0))->values()->toArray(),
                    'categories' => $categories->toArray(),
                    'period' => $period
                ]
            ]
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
     * Get top affiliates chart (horizontal bars)
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
            ->limit(5)
            ->get();

        return [
            'items' => $topAffiliates->map(function ($affiliate) {
                return [
                    'label' => $affiliate->name,
                    'labelKey' => null,
                    'value' => (float) $affiliate->total_commissions
                ];
            })->toArray()
        ];
    }

    /**
     * Get top products chart (pie/doughnut)
     */
    private function getTopProductsChart(array $filters): array
    {
        $topProducts = DB::table('commande_articles')
            ->join('produits', 'commande_articles.produit_id', '=', 'produits.id')
            ->select([
                'produits.titre as name',
                DB::raw('COUNT(commande_articles.id) as orders_count'),
                DB::raw('SUM(commande_articles.total_ligne) as revenue'),
            ])
            ->groupBy('produits.id', 'produits.titre')
            ->orderBy('orders_count', 'desc')
            ->limit(5)
            ->get();

        return [
            'items' => $topProducts->map(function ($product) {
                return [
                    'label' => $product->name,
                    'labelKey' => null,
                    'value' => (int) $product->orders_count
                ];
            })->toArray()
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
     * Get recent payments table data
     */
    private function getRecentPayments(array $filters): array
    {
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        $query = Withdrawal::with('user:id,nom_complet')
            ->select(['id', 'user_id', 'amount', 'status', 'created_at'])
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $payments = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($payment) {
                return [
                    'affiliate' => $payment->user->nom_complet ?? 'Unknown',
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
     * Get monthly paid commissions table data
     */
    private function getMonthlyPaidCommissions(array $filters): array
    {
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;
        $thisMonth = Carbon::now()->startOfMonth();

        $query = CommissionAffilie::with('user:id,nom_complet')
            ->where('status', 'paid')
            ->where('paid_at', '>=', $thisMonth)
            ->select(['id', 'user_id', 'amount', 'paid_at'])
            ->orderBy('paid_at', 'desc');

        $total = $query->count();
        $commissions = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($commission) {
                return [
                    'affiliate' => $commission->user->nom_complet ?? 'Unknown',
                    'amount' => (float) $commission->amount,
                    'date' => $commission->paid_at->toISOString(),
                ];
            });

        return [
            'rows' => $commissions->toArray(),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total
            ]
        ];
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
