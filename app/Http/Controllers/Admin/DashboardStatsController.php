<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Models\Produit;
use App\Models\Boutique;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardStatsController extends Controller
{
    /**
     * Get admin dashboard statistics
     */
    public function getDashboardStats(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => __('messages.access_denied')], Response::HTTP_FORBIDDEN);
        }

        $stats = [
            'overview' => $this->getOverviewStats(),
            'orders' => $this->getOrderStats(),
            'affiliates' => $this->getAffiliateStats(),
            'revenue' => $this->getRevenueStats(),
            'recent_activity' => $this->getRecentActivity(),
        ];

        return response()->json(['stats' => $stats]);
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats()
    {
        return [
            'total_affiliates' => User::role('affiliate')->count(),
            'active_affiliates' => User::role('affiliate')->where('statut', 'actif')->count(),
            'total_orders' => Commande::count(),
            'pending_orders' => Commande::where('statut', 'en_attente')->count(),
            'total_products' => Produit::count(),
            'active_products' => Produit::where('actif', true)->count(),
            'total_boutiques' => Boutique::count(),
            'total_clients' => Client::count(),
        ];
    }

    /**
     * Get order statistics
     */
    private function getOrderStats()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'today' => [
                'total' => Commande::whereDate('created_at', $today)->count(),
                'confirmed' => Commande::whereDate('created_at', $today)->where('statut', 'confirme')->count(),
                'shipped' => Commande::whereDate('created_at', $today)->where('statut', 'expedie')->count(),
                'delivered' => Commande::whereDate('created_at', $today)->where('statut', 'livre')->count(),
            ],
            'this_week' => [
                'total' => Commande::where('created_at', '>=', $thisWeek)->count(),
                'revenue' => Commande::where('created_at', '>=', $thisWeek)->sum('montant_total'),
            ],
            'this_month' => [
                'total' => Commande::where('created_at', '>=', $thisMonth)->count(),
                'revenue' => Commande::where('created_at', '>=', $thisMonth)->sum('montant_total'),
            ],
            'status_distribution' => Commande::select('statut', DB::raw('count(*) as count'))
                ->groupBy('statut')
                ->get()
                ->pluck('count', 'statut'),
        ];
    }

    /**
     * Get affiliate statistics
     */
    private function getAffiliateStats()
    {
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            'new_this_month' => User::role('affiliate')
                ->where('created_at', '>=', $thisMonth)
                ->count(),
            'top_performers' => User::role('affiliate')
                ->withCount(['commandes as orders_count'])
                ->withSum(['commissions as total_commissions'], 'montant')
                ->orderBy('total_commissions', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($affiliate) {
                    return [
                        'id' => $affiliate->id,
                        'nom_complet' => $affiliate->nom_complet,
                        'orders_count' => $affiliate->orders_count ?? 0,
                        'total_commissions' => $affiliate->total_commissions ?? 0,
                    ];
                }),
            'status_distribution' => User::role('affiliate')
                ->select('statut', DB::raw('count(*) as count'))
                ->groupBy('statut')
                ->get()
                ->pluck('count', 'statut'),
        ];
    }

    /**
     * Get revenue statistics
     */
    private function getRevenueStats()
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        $thisMonthRevenue = Commande::where('created_at', '>=', $thisMonth)->sum('montant_total');
        $lastMonthRevenue = Commande::whereBetween('created_at', [$lastMonth, $thisMonth])->sum('montant_total');

        $thisMonthCommissions = CommissionAffilie::where('created_at', '>=', $thisMonth)->sum('montant');
        $lastMonthCommissions = CommissionAffilie::whereBetween('created_at', [$lastMonth, $thisMonth])->sum('montant');

        return [
            'total_revenue' => Commande::sum('montant_total'),
            'this_month_revenue' => $thisMonthRevenue,
            'last_month_revenue' => $lastMonthRevenue,
            'revenue_growth' => $lastMonthRevenue > 0 ? 
                (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0,
            'total_commissions' => CommissionAffilie::sum('montant'),
            'this_month_commissions' => $thisMonthCommissions,
            'last_month_commissions' => $lastMonthCommissions,
            'commission_growth' => $lastMonthCommissions > 0 ? 
                (($thisMonthCommissions - $lastMonthCommissions) / $lastMonthCommissions) * 100 : 0,
            'pending_payments' => CommissionAffilie::where('statut', 'en_attente')->sum('montant'),
        ];
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity()
    {
        return [
            'recent_orders' => Commande::with(['client', 'affilie'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'client_nom' => $order->client->nom ?? 'N/A',
                        'affilie_nom' => $order->affilie->nom_complet ?? 'N/A',
                        'montant_total' => $order->montant_total,
                        'statut' => $order->statut,
                        'created_at' => $order->created_at,
                    ];
                }),
            'recent_affiliates' => User::role('affiliate')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($affiliate) {
                    return [
                        'id' => $affiliate->id,
                        'nom_complet' => $affiliate->nom_complet,
                        'email' => $affiliate->email,
                        'statut' => $affiliate->statut,
                        'created_at' => $affiliate->created_at,
                    ];
                }),
        ];
    }

    /**
     * Get chart data for dashboard
     */
    public function getChartData(Request $request)
    {
        // Check admin permission
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $period = $request->get('period', 'month'); // week, month, year

        $chartData = [
            'orders_chart' => $this->getOrdersChartData($period),
            'revenue_chart' => $this->getRevenueChartData($period),
            'affiliates_chart' => $this->getAffiliatesChartData($period),
        ];

        return response()->json(['chart_data' => $chartData]);
    }

    /**
     * Get orders chart data
     */
    private function getOrdersChartData($period)
    {
        $query = Commande::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        );

        switch ($period) {
            case 'week':
                $query->where('created_at', '>=', Carbon::now()->subWeek());
                break;
            case 'year':
                $query->where('created_at', '>=', Carbon::now()->subYear());
                break;
            default: // month
                $query->where('created_at', '>=', Carbon::now()->subMonth());
        }

        return $query->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                ];
            });
    }

    /**
     * Get revenue chart data
     */
    private function getRevenueChartData($period)
    {
        $query = Commande::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(montant_total) as revenue')
        );

        switch ($period) {
            case 'week':
                $query->where('created_at', '>=', Carbon::now()->subWeek());
                break;
            case 'year':
                $query->where('created_at', '>=', Carbon::now()->subYear());
                break;
            default: // month
                $query->where('created_at', '>=', Carbon::now()->subMonth());
        }

        return $query->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'revenue' => (float) $item->revenue,
                ];
            });
    }

    /**
     * Get affiliates chart data
     */
    private function getAffiliatesChartData($period)
    {
        $query = User::role('affiliate')->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        );

        switch ($period) {
            case 'week':
                $query->where('created_at', '>=', Carbon::now()->subWeek());
                break;
            case 'year':
                $query->where('created_at', '>=', Carbon::now()->subYear());
                break;
            default: // month
                $query->where('created_at', '>=', Carbon::now()->subMonth());
        }

        return $query->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => $item->count,
                ];
            });
    }
}
