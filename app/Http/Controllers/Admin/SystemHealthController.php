<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionAffilie;
use App\Models\ShippingParcel;
use App\Models\Withdrawal;
use App\Models\Commande;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SystemHealthController extends Controller
{
    /**
     * Get system health dashboard data
     */
    public function dashboard(): JsonResponse
    {
        try {
            $data = [
                'commission_health' => $this->getCommissionHealth(),
                'ozonexpress_health' => $this->getOzonExpressHealth(),
                'withdrawal_health' => $this->getWithdrawalHealth(),
                'scheduled_tasks' => $this->getScheduledTasksStatus(),
                'recent_activities' => $this->getRecentActivities(),
                'system_settings' => $this->getSystemSettings(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('System health dashboard error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load system health data'
            ], 500);
        }
    }

    /**
     * Trigger OzonExpress tracking via web interface
     */
    public function runOzonExpressTracking(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 50), 100);
            
            // Run the command
            $exitCode = Artisan::call('ozonexpress:track-parcels', [
                '--limit' => $limit
            ]);

            $output = Artisan::output();

            Log::info('OzonExpress tracking triggered via web', [
                'limit' => $limit,
                'exit_code' => $exitCode,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'OzonExpress tracking completed successfully' : 'OzonExpress tracking failed',
                'output' => $output,
                'exit_code' => $exitCode
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to run OzonExpress tracking', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to run OzonExpress tracking: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Trigger commission processing via web interface
     */
    public function runCommissionProcessing(): JsonResponse
    {
        try {
            // Run the command
            $exitCode = Artisan::call('commissions:process-eligible');
            $output = Artisan::output();

            Log::info('Commission processing triggered via web', [
                'exit_code' => $exitCode,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'Commission processing completed successfully' : 'Commission processing failed',
                'output' => $output,
                'exit_code' => $exitCode
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to run commission processing', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to run commission processing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get commission system health metrics
     */
    private function getCommissionHealth(): array
    {
        $total = CommissionAffilie::count();
        $calculated = CommissionAffilie::where('status', 'calculated')->count();
        $eligible = CommissionAffilie::where('status', 'eligible')->count();
        $paid = CommissionAffilie::where('status', 'paid')->count();
        
        // Recent commission creation rate
        $recentCommissions = CommissionAffilie::where('created_at', '>=', now()->subHours(24))->count();
        
        // Commission accuracy (adjustments vs original)
        $adjustments = CommissionAffilie::where('type', 'adjustment')->count();
        $original = CommissionAffilie::where('type', 'vente')->count();
        $accuracyRate = $original > 0 ? (($original - $adjustments) / $original) * 100 : 100;

        return [
            'total_commissions' => $total,
            'status_breakdown' => [
                'calculated' => $calculated,
                'eligible' => $eligible,
                'paid' => $paid,
            ],
            'recent_24h' => $recentCommissions,
            'accuracy_rate' => round($accuracyRate, 2),
            'health_score' => $this->calculateCommissionHealthScore($accuracyRate, $recentCommissions),
        ];
    }

    /**
     * Get OzonExpress system health metrics
     */
    private function getOzonExpressHealth(): array
    {
        $totalParcels = ShippingParcel::where('provider', 'ozonexpress')->count();
        $pendingParcels = ShippingParcel::where('provider', 'ozonexpress')
            ->whereNotIn('status', ['delivered', 'returned', 'cancelled'])
            ->count();
        $deliveredParcels = ShippingParcel::where('provider', 'ozonexpress')
            ->where('status', 'delivered')
            ->count();
        
        // Recent tracking activity
        $recentlyTracked = ShippingParcel::where('provider', 'ozonexpress')
            ->where('last_synced_at', '>=', now()->subHours(2))
            ->count();

        // Delivery rate
        $deliveryRate = $totalParcels > 0 ? ($deliveredParcels / $totalParcels) * 100 : 0;

        return [
            'total_parcels' => $totalParcels,
            'pending_parcels' => $pendingParcels,
            'delivered_parcels' => $deliveredParcels,
            'delivery_rate' => round($deliveryRate, 2),
            'recently_tracked' => $recentlyTracked,
            'health_score' => $this->calculateOzonExpressHealthScore($pendingParcels, $recentlyTracked),
        ];
    }

    /**
     * Get withdrawal system health metrics
     */
    private function getWithdrawalHealth(): array
    {
        $total = Withdrawal::count();
        $pending = Withdrawal::where('status', 'pending')->count();
        $approved = Withdrawal::where('status', 'approved')->count();
        $paid = Withdrawal::where('status', 'paid')->count();
        
        // Recent withdrawal requests
        $recentRequests = Withdrawal::where('created_at', '>=', now()->subDays(7))->count();
        
        // Average processing time
        $avgProcessingTime = Withdrawal::whereNotNull('approved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, approved_at)) as avg_hours')
            ->value('avg_hours');

        return [
            'total_withdrawals' => $total,
            'status_breakdown' => [
                'pending' => $pending,
                'approved' => $approved,
                'paid' => $paid,
            ],
            'recent_7d' => $recentRequests,
            'avg_processing_hours' => round($avgProcessingTime ?? 0, 1),
            'health_score' => $this->calculateWithdrawalHealthScore($pending, $avgProcessingTime),
        ];
    }

    /**
     * Get scheduled tasks status
     */
    private function getScheduledTasksStatus(): array
    {
        // Check if scheduler is running by looking at recent logs
        $schedulerActive = Cache::get('scheduler_heartbeat', false);
        
        return [
            'scheduler_active' => $schedulerActive,
            'ozonexpress_tracking' => [
                'frequency' => 'Every 30 minutes (8:00-20:00)',
                'last_run' => Cache::get('ozonexpress_tracking_last_run'),
                'status' => 'active'
            ],
            'commission_processing' => [
                'frequency' => 'Every hour',
                'last_run' => Cache::get('commission_processing_last_run'),
                'status' => 'active'
            ],
        ];
    }

    /**
     * Get recent system activities
     */
    private function getRecentActivities(): array
    {
        $activities = [];

        // Recent commissions
        $recentCommissions = CommissionAffilie::with('affiliate:id,nom_complet')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($commission) {
                return [
                    'type' => 'commission_created',
                    'message' => "Commission créée pour {$commission->affiliate->nom_complet}",
                    'amount' => $commission->amount,
                    'created_at' => $commission->created_at,
                ];
            });

        // Recent withdrawals
        $recentWithdrawals = Withdrawal::with('user:id,nom_complet')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($withdrawal) {
                return [
                    'type' => 'withdrawal_requested',
                    'message' => "Demande de retrait par {$withdrawal->user->nom_complet}",
                    'amount' => $withdrawal->amount,
                    'created_at' => $withdrawal->created_at,
                ];
            });

        // Recent deliveries
        $recentDeliveries = ShippingParcel::with('commande')
            ->where('status', 'delivered')
            ->where('updated_at', '>=', now()->subDays(1))
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($parcel) {
                return [
                    'type' => 'parcel_delivered',
                    'message' => "Colis livré: {$parcel->tracking_number}",
                    'tracking_number' => $parcel->tracking_number,
                    'created_at' => $parcel->updated_at,
                ];
            });

        return collect($activities)
            ->merge($recentCommissions)
            ->merge($recentWithdrawals)
            ->merge($recentDeliveries)
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Get system settings
     */
    private function getSystemSettings(): array
    {
        return [
            'commission_strategy' => AppSetting::get('commission.strategy', 'legacy'),
            'commission_trigger_status' => AppSetting::get('commission.trigger_status', 'livree'),
            'commission_cooldown_days' => AppSetting::get('commission.cooldown_days', 7),
        ];
    }

    /**
     * Calculate commission health score
     */
    private function calculateCommissionHealthScore(float $accuracyRate, int $recentCommissions): string
    {
        if ($accuracyRate >= 95 && $recentCommissions > 0) {
            return 'excellent';
        } elseif ($accuracyRate >= 90) {
            return 'good';
        } elseif ($accuracyRate >= 80) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Calculate OzonExpress health score
     */
    private function calculateOzonExpressHealthScore(int $pendingParcels, int $recentlyTracked): string
    {
        if ($pendingParcels < 10 && $recentlyTracked > 0) {
            return 'excellent';
        } elseif ($pendingParcels < 50) {
            return 'good';
        } elseif ($pendingParcels < 100) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Calculate withdrawal health score
     */
    private function calculateWithdrawalHealthScore(int $pending, ?float $avgProcessingTime): string
    {
        if ($pending < 5 && ($avgProcessingTime ?? 0) < 48) {
            return 'excellent';
        } elseif ($pending < 20 && ($avgProcessingTime ?? 0) < 72) {
            return 'good';
        } elseif ($pending < 50) {
            return 'warning';
        } else {
            return 'critical';
        }
    }
}
