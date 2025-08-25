<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Models\Produit;
use App\Models\Categorie;
use App\Models\Boutique;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Services\ReportCacheService;

class SalesReportsController extends Controller
{
    /**
     * Get comprehensive sales reports summary/KPIs
     */
    public function getSummary(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);

        $summary = ReportCacheService::cacheSalesData(
            'summary',
            $filters,
            function () use ($filters) {
                return $this->calculateSummaryKPIs($filters);
            },
            ReportCacheService::getOptimalTTL('summary', $filters)
        );

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get sales time series data for charts
     */
    public function getSeries(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $period = $request->get('period', 'day'); // day, week, month
        $cacheKey = 'sales_reports_series_' . md5(serialize($filters) . $period);

        $series = Cache::remember($cacheKey, 300, function () use ($filters, $period) {
            return [
                'sales_over_time' => $this->getSalesOverTimeData($period, $filters),
                'orders_over_time' => $this->getOrdersOverTimeData($period, $filters),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $series,
        ]);
    }

    /**
     * Get status breakdown data
     */
    public function getStatusBreakdown(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $cacheKey = 'sales_reports_status_' . md5(serialize($filters));

        $breakdown = Cache::remember($cacheKey, 300, function () use ($filters) {
            return $this->getOrdersByStatusData($filters);
        });

        return response()->json([
            'success' => true,
            'data' => $breakdown,
        ]);
    }

    /**
     * Get top products by revenue
     */
    public function getTopProducts(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $limit = min((int) $request->get('limit', 10), 50);
        $cacheKey = 'sales_reports_top_products_' . md5(serialize($filters) . $limit);

        $products = Cache::remember($cacheKey, 300, function () use ($filters, $limit) {
            return $this->getTopProductsData($filters, $limit);
        });

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Get orders table data with pagination
     */
    public function getOrders(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $orders = $this->getOrdersTableData($filters);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get top affiliates by sales
     */
    public function getTopAffiliates(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->parseFilters($request);
        $limit = min((int) $request->get('limit', 10), 50);
        $cacheKey = 'sales_reports_top_affiliates_' . md5(serialize($filters) . $limit);

        $affiliates = Cache::remember($cacheKey, 300, function () use ($filters, $limit) {
            return $this->getTopAffiliatesData($filters, $limit);
        });

        return response()->json([
            'success' => true,
            'data' => $affiliates,
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
            'affiliate_ids' => $request->get('affiliate_ids', []), // Multi-select
            'status' => $request->get('status'),
            'country' => $request->get('country'),
            'city' => $request->get('city'),
            'product_ids' => $request->get('product_ids', []),
            'category_ids' => $request->get('category_ids', []),
            'boutique_ids' => $request->get('boutique_ids', []),
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

        // Build base query with filters
        $currentQuery = $this->buildOrdersQuery($filters);
        $prevQuery = $this->buildOrdersQuery([
            ...$filters,
            'date_start' => $prevStartDate->toDateString(),
            'date_end' => $prevEndDate->toDateString(),
        ]);

        // Current period metrics
        $currentStats = $currentQuery->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN statut = "livre" THEN total_ttc ELSE 0 END), 0) as total_sales,
            COALESCE(SUM(CASE WHEN statut = "livre" THEN 1 ELSE 0 END), 0) as delivered_orders,
            COALESCE(SUM(CASE WHEN statut IN ("echec", "retour") THEN 1 ELSE 0 END), 0) as failed_returned_orders,
            COALESCE(AVG(CASE WHEN statut = "livre" THEN total_ttc ELSE NULL END), 0) as avg_order_value
        ')->first();

        // Previous period metrics
        $prevStats = $prevQuery->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN statut = "livre" THEN total_ttc ELSE 0 END), 0) as total_sales,
            COALESCE(SUM(CASE WHEN statut = "livre" THEN 1 ELSE 0 END), 0) as delivered_orders
        ')->first();

        // Commission metrics
        $currentCommissions = CommissionAffilie::whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount') ?? 0;
        $prevCommissions = CommissionAffilie::whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->sum('amount') ?? 0;

        // Calculate rates and deltas
        $deliveredRate = $currentStats->total_orders > 0 
            ? ($currentStats->delivered_orders / $currentStats->total_orders) * 100 
            : 0;
        
        $returnRate = $currentStats->delivered_orders > 0 
            ? ($currentStats->failed_returned_orders / $currentStats->delivered_orders) * 100 
            : 0;

        return [
            'total_sales' => [
                'value' => (float) $currentStats->total_sales,
                'delta' => $this->calculateDelta($currentStats->total_sales, $prevStats->total_sales),
                'currency' => 'MAD',
            ],
            'orders_count' => [
                'value' => (int) $currentStats->total_orders,
                'delta' => $this->calculateDelta($currentStats->total_orders, $prevStats->total_orders),
            ],
            'avg_order_value' => [
                'value' => (float) $currentStats->avg_order_value,
                'delta' => null, // Complex to calculate meaningfully
                'currency' => 'MAD',
            ],
            'delivered_rate' => [
                'value' => round($deliveredRate, 2),
                'delta' => null, // Would need previous period calculation
                'unit' => '%',
            ],
            'return_rate' => [
                'value' => round($returnRate, 2),
                'delta' => null,
                'unit' => '%',
            ],
            'commissions_accrued' => [
                'value' => (float) $currentCommissions,
                'delta' => $this->calculateDelta($currentCommissions, $prevCommissions),
                'currency' => 'MAD',
            ],
        ];
    }

    /**
     * Build base orders query with filters
     */
    private function buildOrdersQuery(array $filters)
    {
        $query = Commande::with(['affiliate:id,nom_complet', 'boutique:id,nom', 'client:id,nom_complet'])
            ->whereBetween('created_at', [$filters['date_start'], $filters['date_end']]);

        // Apply filters
        if (!empty($filters['affiliate_ids'])) {
            $query->whereIn('user_id', $filters['affiliate_ids']);
        }

        if (!empty($filters['status'])) {
            $query->where('statut', $filters['status']);
        }

        if (!empty($filters['boutique_ids'])) {
            $query->whereIn('boutique_id', $filters['boutique_ids']);
        }

        // Add product/category filters via joins if needed
        if (!empty($filters['product_ids']) || !empty($filters['category_ids'])) {
            $query->join('commande_articles', 'commandes.id', '=', 'commande_articles.commande_id')
                  ->join('produits', 'commande_articles.produit_id', '=', 'produits.id');

            if (!empty($filters['product_ids'])) {
                $query->whereIn('produits.id', $filters['product_ids']);
            }

            if (!empty($filters['category_ids'])) {
                $query->whereIn('produits.categorie_id', $filters['category_ids']);
            }

            $query->select('commandes.*')->distinct();
        }

        return $query;
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
     * Get sales over time data for charts
     */
    private function getSalesOverTimeData(string $period, array $filters): array
    {
        $dateFormat = $this->getDateFormat($period);
        $dateRange = [$filters['date_start'], $filters['date_end']];

        $salesData = $this->buildOrdersQuery($filters)
            ->where('statut', 'livre') // Only delivered orders
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COALESCE(SUM(total_ttc), 0) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $labels = $this->generateDateLabels($period, $dateRange);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Sales (MAD)',
                    'data' => array_map(fn($label) => (float) ($salesData[$label] ?? 0), $labels),
                    'borderColor' => '#7367F0',
                    'backgroundColor' => 'rgba(115, 103, 240, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * Get orders over time data for charts
     */
    private function getOrdersOverTimeData(string $period, array $filters): array
    {
        $dateFormat = $this->getDateFormat($period);
        $dateRange = [$filters['date_start'], $filters['date_end']];

        $ordersData = $this->buildOrdersQuery($filters)
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
                    'label' => 'Orders Count',
                    'data' => array_map(fn($label) => (int) ($ordersData[$label] ?? 0), $labels),
                    'borderColor' => '#28C76F',
                    'backgroundColor' => 'rgba(40, 199, 111, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * Get orders by status breakdown
     */
    private function getOrdersByStatusData(array $filters): array
    {
        $statusData = $this->buildOrdersQuery($filters)
            ->select('statut', DB::raw('count(*) as count'))
            ->groupBy('statut')
            ->get();

        $statusMap = [
            'livre' => 'Delivered',
            'echec' => 'Failed',
            'retour' => 'Returned',
            'annule' => 'Canceled',
            'confirme' => 'Confirmed',
            'en_attente' => 'Pending',
        ];

        return [
            'labels' => $statusData->pluck('statut')->map(fn($status) => $statusMap[$status] ?? $status)->toArray(),
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $statusData->pluck('count')->map(fn($val) => (int) $val)->toArray(),
                    'backgroundColor' => [
                        '#28C76F', // Delivered - Green
                        '#EA5455', // Failed - Red
                        '#FF9F43', // Returned - Orange
                        '#6C757D', // Canceled - Gray
                        '#7367F0', // Confirmed - Purple
                        '#00CFE8', // Pending - Cyan
                    ],
                ],
            ],
        ];
    }

    /**
     * Get top products by revenue
     */
    private function getTopProductsData(array $filters, int $limit): array
    {
        $products = DB::table('commandes')
            ->join('commande_articles', 'commandes.id', '=', 'commande_articles.commande_id')
            ->join('produits', 'commande_articles.produit_id', '=', 'produits.id')
            ->whereBetween('commandes.created_at', [$filters['date_start'], $filters['date_end']])
            ->where('commandes.statut', 'livre') // Only delivered orders
            ->select([
                'produits.id',
                'produits.titre as name',
                DB::raw('COUNT(DISTINCT commandes.id) as orders_count'),
                DB::raw('SUM(commande_articles.quantite) as total_quantity'),
                DB::raw('COALESCE(SUM(commande_articles.prix_unitaire * commande_articles.quantite), 0) as total_revenue'),
            ])
            ->groupBy('produits.id', 'produits.titre')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'orders_count' => (int) $product->orders_count,
                    'total_quantity' => (int) $product->total_quantity,
                    'total_revenue' => (float) $product->total_revenue,
                ];
            })
            ->toArray();

        return [
            'labels' => array_column($products, 'name'),
            'datasets' => [
                [
                    'label' => 'Revenue (MAD)',
                    'data' => array_column($products, 'total_revenue'),
                    'backgroundColor' => '#7367F0',
                ],
            ],
            'table_data' => $products,
        ];
    }

    /**
     * Get orders table data with pagination
     */
    private function getOrdersTableData(array $filters): array
    {
        $query = $this->buildOrdersQuery($filters);

        $total = $query->count();
        $orders = $query->with(['articles.produit:id,titre', 'commissions'])
            ->select([
                'commandes.id',
                'commandes.created_at',
                'commandes.statut',
                'commandes.total_ttc',
                'commandes.user_id',
                'commandes.client_id',
            ])
            ->orderBy('created_at', 'desc')
            ->offset(($filters['page'] - 1) * $filters['per_page'])
            ->limit($filters['per_page'])
            ->get()
            ->map(function ($order) {
                $commission = $order->commissions->sum('amount');
                $itemsCount = $order->articles->count();

                return [
                    'id' => $order->id,
                    'order_ref' => 'ORD-' . substr($order->id, 0, 8),
                    'date' => $order->created_at->format('Y-m-d H:i'),
                    'affiliate_name' => $order->affiliate->nom_complet ?? 'Unknown',
                    'customer_name' => $order->client ? substr($order->client->nom_complet, 0, 3) . '***' : 'Guest',
                    'status' => $order->statut,
                    'items_count' => $itemsCount,
                    'subtotal' => (float) $order->total_ttc, // Simplified for now
                    'shipping' => 0, // Would need shipping calculation
                    'total' => (float) $order->total_ttc,
                    'commission' => (float) $commission,
                ];
            })
            ->toArray();

        return [
            'data' => $orders,
            'pagination' => [
                'current_page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => $total,
                'last_page' => ceil($total / $filters['per_page']),
            ],
        ];
    }

    /**
     * Get top affiliates by sales
     */
    private function getTopAffiliatesData(array $filters, int $limit): array
    {
        $affiliates = User::role('affiliate')
            ->select([
                'users.id',
                'users.nom_complet as name',
                'users.email',
                DB::raw('COUNT(DISTINCT commandes.id) as orders_count'),
                DB::raw('COALESCE(SUM(CASE WHEN commandes.statut = "livre" THEN commandes.total_ttc ELSE 0 END), 0) as total_sales'),
                DB::raw('COALESCE(SUM(CASE WHEN commandes.statut = "livre" THEN 1 ELSE 0 END), 0) as delivered_orders'),
                DB::raw('COALESCE(AVG(CASE WHEN commandes.statut = "livre" THEN commandes.total_ttc ELSE NULL END), 0) as avg_order_value'),
                DB::raw('COALESCE(SUM(commissions_affilies.amount), 0) as total_commission'),
            ])
            ->leftJoin('commandes', function ($join) use ($filters) {
                $join->on('users.id', '=', 'commandes.user_id')
                     ->whereBetween('commandes.created_at', [$filters['date_start'], $filters['date_end']]);
            })
            ->leftJoin('commissions_affilies', 'commandes.id', '=', 'commissions_affilies.commande_id')
            ->groupBy('users.id', 'users.nom_complet', 'users.email')
            ->orderBy('total_sales', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($affiliate) {
                $deliveredRate = $affiliate->orders_count > 0
                    ? ($affiliate->delivered_orders / $affiliate->orders_count) * 100
                    : 0;

                return [
                    'id' => $affiliate->id,
                    'name' => $affiliate->name,
                    'email' => $affiliate->email,
                    'orders_count' => (int) $affiliate->orders_count,
                    'total_sales' => (float) $affiliate->total_sales,
                    'delivered_rate' => round($deliveredRate, 2),
                    'avg_order_value' => (float) $affiliate->avg_order_value,
                    'total_commission' => (float) $affiliate->total_commission,
                ];
            })
            ->toArray();

        return $affiliates;
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
