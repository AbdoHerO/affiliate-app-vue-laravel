<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Models\ReferralAttribution;
use App\Models\ReferralClick;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AffiliatePerformanceController extends Controller
{
    /**
     * Get affiliate performance summary/KPIs
     */
    public function getSummary(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $cacheKey = 'affiliate_performance_summary_' . md5(serialize($filters));

        $summary = Cache::remember($cacheKey, 300, function () use ($filters) {
            return $this->calculateSummaryKPIs($filters);
        });

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get commission and payout series data for charts
     */
    public function getSeries(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $period = $request->get('period', 'day'); // day, week, month
        $cacheKey = 'affiliate_performance_series_' . md5(serialize($filters) . $period);

        $series = Cache::remember($cacheKey, 300, function () use ($filters, $period) {
            return [
                'commissions_over_time' => $this->getCommissionsOverTimeData($period, $filters),
                'payouts_over_time' => $this->getPayoutsOverTimeData($period, $filters),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $series,
        ]);
    }

    /**
     * Get affiliate leaderboard data
     */
    public function getLeaderboard(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $sortBy = $request->get('sort_by', 'commission'); // commission, sales, orders
        $limit = min((int) $request->get('limit', 20), 100);
        
        $cacheKey = 'affiliate_performance_leaderboard_' . md5(serialize($filters) . $sortBy . $limit);

        $leaderboard = Cache::remember($cacheKey, 300, function () use ($filters, $sortBy, $limit) {
            return $this->getLeaderboardData($filters, $sortBy, $limit);
        });

        return response()->json([
            'success' => true,
            'data' => $leaderboard,
        ]);
    }

    /**
     * Get commission ledger with pagination
     */
    public function getLedger(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $ledger = $this->getCommissionLedgerData($filters);

        return response()->json([
            'success' => true,
            'data' => $ledger,
        ]);
    }

    /**
     * Get performance segments (cohorts)
     */
    public function getSegments(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $cacheKey = 'affiliate_performance_segments_' . md5(serialize($filters));

        $segments = Cache::remember($cacheKey, 600, function () use ($filters) {
            return $this->getPerformanceSegments($filters);
        });

        return response()->json([
            'success' => true,
            'data' => $segments,
        ]);
    }

    /**
     * Parse request filters with validation
     */
    private function parseFilters(Request $request): array
    {
        // Date range handling
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
        
        $dateStart = $dateStart ?: Carbon::now()->subDays(30)->toDateString();
        $dateEnd = $dateEnd ?: Carbon::now()->toDateString();

        return [
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
            'affiliate_status' => $request->get('affiliate_status'), // approved, blocked, pending
            'country' => $request->get('country'),
            'city' => $request->get('city'),
            'min_orders' => (int) $request->get('min_orders', 0),
            'min_commission' => (float) $request->get('min_commission', 0),
            'page' => max(1, (int) $request->get('page', 1)),
            'per_page' => min((int) $request->get('per_page', 15), 100),
        ];
    }

    /**
     * Calculate summary KPIs with delta vs previous period
     */
    private function calculateSummaryKPIs(array $filters): array
    {
        $startDate = Carbon::parse($filters['date_start']);
        $endDate = Carbon::parse($filters['date_end']);
        $daysDiff = $startDate->diffInDays($endDate);
        
        // Previous period for comparison
        $prevStartDate = $startDate->copy()->subDays($daysDiff + 1);
        $prevEndDate = $startDate->copy()->subDay();

        // Active affiliates (with at least 1 order or commission in range)
        $activeAffiliates = User::role('affiliate')
            ->whereHas('commandes', function ($query) use ($filters) {
                $query->whereBetween('created_at', [$filters['date_start'], $filters['date_end']]);
            })
            ->count();

        $prevActiveAffiliates = User::role('affiliate')
            ->whereHas('commandes', function ($query) use ($prevStartDate, $prevEndDate) {
                $query->whereBetween('created_at', [$prevStartDate, $prevEndDate]);
            })
            ->count();

        // New affiliates (joined in range)
        $newAffiliates = User::role('affiliate')
            ->whereBetween('created_at', [$filters['date_start'], $filters['date_end']])
            ->count();

        $prevNewAffiliates = User::role('affiliate')
            ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->count();

        // Total commissions created in range
        $totalCommissions = CommissionAffilie::whereBetween('created_at', [$filters['date_start'], $filters['date_end']])
            ->sum('amount') ?? 0;

        $prevTotalCommissions = CommissionAffilie::whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->sum('amount') ?? 0;

        // Payouts (withdrawals paid in range)
        $totalPayouts = Withdrawal::where('status', 'paid')
            ->whereBetween('paid_at', [$filters['date_start'], $filters['date_end']])
            ->sum('amount') ?? 0;

        $prevTotalPayouts = Withdrawal::where('status', 'paid')
            ->whereBetween('paid_at', [$prevStartDate, $prevEndDate])
            ->sum('amount') ?? 0;

        // Conversion rate (simplified: delivered orders / total orders)
        $totalOrders = Commande::whereBetween('created_at', [$filters['date_start'], $filters['date_end']])
            ->count();
        $deliveredOrders = Commande::whereBetween('created_at', [$filters['date_start'], $filters['date_end']])
            ->where('statut', 'livre')
            ->count();
        
        $conversionRate = $totalOrders > 0 ? ($deliveredOrders / $totalOrders) * 100 : 0;

        return [
            'active_affiliates' => [
                'value' => $activeAffiliates,
                'delta' => $this->calculateDelta($activeAffiliates, $prevActiveAffiliates),
            ],
            'new_affiliates' => [
                'value' => $newAffiliates,
                'delta' => $this->calculateDelta($newAffiliates, $prevNewAffiliates),
            ],
            'total_commissions' => [
                'value' => (float) $totalCommissions,
                'delta' => $this->calculateDelta($totalCommissions, $prevTotalCommissions),
                'currency' => 'MAD',
            ],
            'total_payouts' => [
                'value' => (float) $totalPayouts,
                'delta' => $this->calculateDelta($totalPayouts, $prevTotalPayouts),
                'currency' => 'MAD',
            ],
            'conversion_rate' => [
                'value' => round($conversionRate, 2),
                'delta' => null, // Would need previous period calculation
                'unit' => '%',
            ],
        ];
    }

    /**
     * Calculate percentage delta between current and previous values
     */
    private function calculateDelta($current, $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get commissions over time data for charts
     */
    private function getCommissionsOverTimeData(string $period, array $filters): array
    {
        $dateFormat = $this->getDateFormat($period);
        $dateRange = [$filters['date_start'], $filters['date_end']];

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
                    'label' => 'Commissions (MAD)',
                    'data' => array_map(fn($label) => (float) ($commissionsData[$label] ?? 0), $labels),
                    'borderColor' => '#7367F0',
                    'backgroundColor' => 'rgba(115, 103, 240, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * Get payouts over time data for charts
     */
    private function getPayoutsOverTimeData(string $period, array $filters): array
    {
        $dateFormat = $this->getDateFormat($period);
        $dateRange = [$filters['date_start'], $filters['date_end']];

        $payoutsData = Withdrawal::where('status', 'paid')
            ->whereBetween('paid_at', $dateRange)
            ->selectRaw("DATE_FORMAT(paid_at, '{$dateFormat}') as date, COALESCE(SUM(amount), 0) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $labels = $this->generateDateLabels($period, $dateRange);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Payouts (MAD)',
                    'data' => array_map(fn($label) => (float) ($payoutsData[$label] ?? 0), $labels),
                    'borderColor' => '#28C76F',
                    'backgroundColor' => 'rgba(40, 199, 111, 0.1)',
                    'fill' => false,
                ],
            ],
        ];
    }

    /**
     * Get leaderboard data
     */
    private function getLeaderboardData(array $filters, string $sortBy, int $limit): array
    {
        $query = User::role('affiliate')
            ->select([
                'users.id',
                'users.nom_complet as name',
                'users.email',
                'users.created_at as joined_at',
                'users.updated_at as last_activity',
                DB::raw('COUNT(DISTINCT commandes.id) as orders_count'),
                DB::raw('COALESCE(SUM(CASE WHEN commandes.statut = "livre" THEN 1 ELSE 0 END), 0) as delivered_orders'),
                DB::raw('COALESCE(SUM(CASE WHEN commandes.statut = "livre" THEN commandes.total_ttc ELSE 0 END), 0) as total_sales'),
                DB::raw('COALESCE(SUM(commissions_affilies.amount), 0) as total_commission'),
                DB::raw('COALESCE(SUM(CASE WHEN withdrawals.status = "paid" THEN withdrawals.amount ELSE 0 END), 0) as total_payouts'),
                DB::raw('COALESCE(AVG(CASE WHEN commandes.statut = "livre" THEN commandes.total_ttc ELSE NULL END), 0) as avg_order_value'),
                DB::raw('COALESCE(SUM(CASE WHEN commandes.statut IN ("retour", "echec") THEN 1 ELSE 0 END), 0) as returns_count'),
            ])
            ->leftJoin('commandes', function ($join) use ($filters) {
                $join->on('users.id', '=', 'commandes.user_id')
                     ->whereBetween('commandes.created_at', [$filters['date_start'], $filters['date_end']]);
            })
            ->leftJoin('commissions_affilies', function ($join) use ($filters) {
                $join->on('commandes.id', '=', 'commissions_affilies.commande_id')
                     ->whereBetween('commissions_affilies.created_at', [$filters['date_start'], $filters['date_end']]);
            })
            ->leftJoin('withdrawals', function ($join) use ($filters) {
                $join->on('users.id', '=', 'withdrawals.user_id')
                     ->where('withdrawals.status', 'paid')
                     ->whereBetween('withdrawals.paid_at', [$filters['date_start'], $filters['date_end']]);
            })
            ->groupBy('users.id', 'users.nom_complet', 'users.email', 'users.created_at', 'users.updated_at');

        // Apply filters
        if ($filters['min_orders'] > 0) {
            $query->having('orders_count', '>=', $filters['min_orders']);
        }

        if ($filters['min_commission'] > 0) {
            $query->having('total_commission', '>=', $filters['min_commission']);
        }

        // Apply sorting
        $sortColumn = match ($sortBy) {
            'sales' => 'total_sales',
            'orders' => 'orders_count',
            'payouts' => 'total_payouts',
            default => 'total_commission',
        };

        $affiliates = $query->orderBy($sortColumn, 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($affiliate) {
                $deliveredRate = $affiliate->orders_count > 0
                    ? ($affiliate->delivered_orders / $affiliate->orders_count) * 100
                    : 0;

                $returnRate = $affiliate->delivered_orders > 0
                    ? ($affiliate->returns_count / $affiliate->delivered_orders) * 100
                    : 0;

                return [
                    'id' => $affiliate->id,
                    'name' => $affiliate->name,
                    'email' => $affiliate->email,
                    'orders_count' => (int) $affiliate->orders_count,
                    'delivered_rate' => round($deliveredRate, 2),
                    'total_sales' => (float) $affiliate->total_sales,
                    'total_commission' => (float) $affiliate->total_commission,
                    'total_payouts' => (float) $affiliate->total_payouts,
                    'avg_order_value' => (float) $affiliate->avg_order_value,
                    'return_rate' => round($returnRate, 2),
                    'last_activity' => $affiliate->last_activity,
                ];
            })
            ->toArray();

        return $affiliates;
    }

    /**
     * Get commission ledger data with pagination
     */
    private function getCommissionLedgerData(array $filters): array
    {
        $query = CommissionAffilie::with(['commande:id,created_at', 'affiliate:id,nom_complet', 'commandeArticle.produit:id,titre'])
            ->whereBetween('created_at', [$filters['date_start'], $filters['date_end']]);

        // Apply affiliate filter if specified
        if (!empty($filters['affiliate_ids'])) {
            $query->whereIn('user_id', $filters['affiliate_ids']);
        }

        $total = $query->count();
        $commissions = $query->select([
                'id',
                'user_id',
                'commande_id',
                'commande_article_id',
                'type',
                'base_amount',
                'rate',
                'amount',
                'status',
                'rule_code',
                'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->offset(($filters['page'] - 1) * $filters['per_page'])
            ->limit($filters['per_page'])
            ->get()
            ->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'affiliate_name' => $commission->affiliate->nom_complet ?? 'Unknown',
                    'order_ref' => 'ORD-' . substr($commission->commande_id, 0, 8),
                    'product_name' => $commission->commandeArticle->produit->titre ?? 'Unknown',
                    'type' => $commission->type,
                    'base_amount' => (float) $commission->base_amount,
                    'rate' => (float) $commission->rate,
                    'commission' => (float) $commission->amount,
                    'status' => $commission->status,
                    'rule_code' => $commission->rule_code,
                    'date' => $commission->created_at->format('Y-m-d H:i'),
                ];
            })
            ->toArray();

        return [
            'data' => $commissions,
            'pagination' => [
                'current_page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => $total,
                'last_page' => ceil($total / $filters['per_page']),
            ],
        ];
    }

    /**
     * Get performance segments (cohorts)
     */
    private function getPerformanceSegments(array $filters): array
    {
        $dateRange = [$filters['date_start'], $filters['date_end']];

        // Top Earners (highest commissions)
        $topEarners = User::role('affiliate')
            ->select('users.id')
            ->join('commissions_affilies', 'users.id', '=', 'commissions_affilies.user_id')
            ->whereBetween('commissions_affilies.created_at', $dateRange)
            ->groupBy('users.id')
            ->havingRaw('SUM(commissions_affilies.amount) >= ?', [1000]) // Top threshold
            ->count();

        // Rising (MoM commission growth >= 20%)
        $thisMonth = Carbon::parse($filters['date_end'])->startOfMonth();
        $lastMonth = $thisMonth->copy()->subMonth();

        $rising = User::role('affiliate')
            ->whereHas('commissions', function ($query) use ($thisMonth) {
                $query->where('created_at', '>=', $thisMonth);
            })
            ->whereHas('commissions', function ($query) use ($lastMonth, $thisMonth) {
                $query->whereBetween('created_at', [$lastMonth, $thisMonth->copy()->subSecond()]);
            })
            ->get()
            ->filter(function ($user) use ($thisMonth, $lastMonth) {
                $thisMonthCommissions = $user->commissions()
                    ->where('created_at', '>=', $thisMonth)
                    ->sum('amount');

                $lastMonthCommissions = $user->commissions()
                    ->whereBetween('created_at', [$lastMonth, $thisMonth->copy()->subSecond()])
                    ->sum('amount');

                if ($lastMonthCommissions == 0) return $thisMonthCommissions > 0;

                $growth = (($thisMonthCommissions - $lastMonthCommissions) / $lastMonthCommissions) * 100;
                return $growth >= 20;
            })
            ->count();

        // At Risk (high return/failure rate > 15%)
        $atRisk = User::role('affiliate')
            ->whereHas('commandes', function ($query) use ($dateRange) {
                $query->whereBetween('created_at', $dateRange);
            })
            ->get()
            ->filter(function ($user) use ($dateRange) {
                $totalOrders = $user->commandes()
                    ->whereBetween('created_at', $dateRange)
                    ->count();

                $failedOrders = $user->commandes()
                    ->whereBetween('created_at', $dateRange)
                    ->whereIn('statut', ['retour', 'echec'])
                    ->count();

                if ($totalOrders == 0) return false;

                $failureRate = ($failedOrders / $totalOrders) * 100;
                return $failureRate > 15;
            })
            ->count();

        // Dormant (no orders in last 30 days)
        $dormantDate = Carbon::now()->subDays(30);
        $dormant = User::role('affiliate')
            ->whereDoesntHave('commandes', function ($query) use ($dormantDate) {
                $query->where('created_at', '>=', $dormantDate);
            })
            ->count();

        return [
            'top_earners' => [
                'count' => $topEarners,
                'description' => 'Affiliates with commissions >= 1000 MAD',
                'filter' => ['min_commission' => 1000],
            ],
            'rising' => [
                'count' => $rising,
                'description' => 'MoM commission growth >= 20%',
                'filter' => ['segment' => 'rising'],
            ],
            'at_risk' => [
                'count' => $atRisk,
                'description' => 'High return/failure rate > 15%',
                'filter' => ['segment' => 'at_risk'],
            ],
            'dormant' => [
                'count' => $dormant,
                'description' => 'No orders in last 30 days',
                'filter' => ['segment' => 'dormant'],
            ],
        ];
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
