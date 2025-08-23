<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionAffilie;
use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * Admin controller for commission backfill monitoring and reports
 */
class CommissionBackfillController extends Controller
{
    /**
     * Display commission backfill dashboard
     */
    public function index()
    {
        // Get commission strategy setting
        $strategy = AppSetting::get('commission.strategy', 'legacy');
        
        // Get recent backfill reports
        $reports = $this->getRecentReports();
        
        // Get commission statistics
        $stats = $this->getCommissionStats();
        
        return response()->json([
            'strategy' => $strategy,
            'reports' => $reports,
            'statistics' => $stats,
        ]);
    }

    /**
     * Get commission statistics
     */
    public function statistics()
    {
        $stats = $this->getCommissionStats();
        return response()->json($stats);
    }

    /**
     * Download backfill CSV report
     */
    public function downloadReport(Request $request)
    {
        $batchId = $request->get('batch_id');
        
        if (!$batchId) {
            return response()->json(['error' => 'Batch ID required'], 400);
        }

        $filename = "commission_backfill_{$batchId}.csv";
        $filepath = "commission_backfills/{$filename}";

        if (!Storage::disk('local')->exists($filepath)) {
            return response()->json(['error' => 'Report not found'], 404);
        }

        return Storage::disk('local')->download($filepath, $filename);
    }

    /**
     * Get recent backfill reports
     */
    private function getRecentReports(): array
    {
        $files = Storage::disk('local')->files('commission_backfills');
        $reportFiles = array_filter($files, fn($file) => str_contains($file, 'report_'));
        
        if (empty($reportFiles)) {
            return [];
        }

        // Sort by filename (which contains timestamp)
        rsort($reportFiles);
        $recentFiles = array_slice($reportFiles, 0, 10);

        $reports = [];
        foreach ($recentFiles as $file) {
            $content = Storage::disk('local')->get($file);
            $report = json_decode($content, true);
            
            if ($report) {
                $reports[] = [
                    'batch_id' => $report['batch_id'],
                    'timestamp' => $report['timestamp'],
                    'dry_run' => $report['dry_run'],
                    'metrics' => $report['metrics'],
                    'summary' => $report['summary'],
                    'has_csv' => Storage::disk('local')->exists("commission_backfills/commission_backfill_{$report['batch_id']}.csv"),
                ];
            }
        }

        return $reports;
    }

    /**
     * Get commission statistics
     */
    private function getCommissionStats(): array
    {
        // Total commissions
        $totalCommissions = CommissionAffilie::count();
        
        // Commissions by type
        $commissionsByType = CommissionAffilie::selectRaw('type, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Commissions by rule code
        $commissionsByRule = CommissionAffilie::selectRaw('rule_code, COUNT(*) as count, SUM(amount) as total_amount')
            ->whereNotNull('rule_code')
            ->groupBy('rule_code')
            ->orderBy('count', 'desc')
            ->get();

        // Recent adjustments (last 30 days)
        $recentAdjustments = CommissionAffilie::where('type', 'adjustment')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Commission accuracy (non-adjustment vs adjustment ratio)
        $regularCommissions = CommissionAffilie::where('type', '!=', 'adjustment')->count();
        $adjustmentCommissions = CommissionAffilie::where('type', 'adjustment')->count();
        $accuracyRate = $totalCommissions > 0 ? (($regularCommissions / $totalCommissions) * 100) : 100;

        return [
            'total_commissions' => $totalCommissions,
            'regular_commissions' => $regularCommissions,
            'adjustment_commissions' => $adjustmentCommissions,
            'accuracy_rate' => round($accuracyRate, 2),
            'commissions_by_type' => $commissionsByType,
            'commissions_by_rule' => $commissionsByRule,
            'recent_adjustments' => $recentAdjustments,
            'total_commission_amount' => CommissionAffilie::sum('amount'),
            'total_adjustment_amount' => CommissionAffilie::where('type', 'adjustment')->sum('amount'),
        ];
    }

    /**
     * Update commission strategy setting
     */
    public function updateStrategy(Request $request)
    {
        $request->validate([
            'strategy' => 'required|in:legacy,margin',
        ]);

        $strategy = $request->get('strategy');
        AppSetting::set('commission.strategy', $strategy);

        return response()->json([
            'success' => true,
            'message' => "Commission strategy updated to: {$strategy}",
            'strategy' => $strategy,
        ]);
    }

    /**
     * Validate commission calculations for a sample of records
     */
    public function validateSample(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        // Get a sample of recent commissions
        $commissions = CommissionAffilie::with(['commande.articles.produit', 'commandeArticle.produit'])
            ->where('type', '!=', 'adjustment')
            ->latest()
            ->limit($limit)
            ->get();

        $validations = [];
        foreach ($commissions as $commission) {
            $validation = $this->validateCommissionCalculation($commission);
            $validations[] = $validation;
        }

        $correctCount = collect($validations)->where('is_correct', true)->count();
        $accuracyRate = count($validations) > 0 ? ($correctCount / count($validations)) * 100 : 100;

        return response()->json([
            'validations' => $validations,
            'summary' => [
                'total_checked' => count($validations),
                'correct' => $correctCount,
                'incorrect' => count($validations) - $correctCount,
                'accuracy_rate' => round($accuracyRate, 2),
            ],
        ]);
    }

    /**
     * Validate a single commission calculation
     */
    private function validateCommissionCalculation(CommissionAffilie $commission): array
    {
        $article = $commission->commandeArticle;
        $product = $article ? $article->produit : null;

        if (!$article || !$product) {
            return [
                'commission_id' => $commission->id,
                'is_correct' => false,
                'error' => 'Missing article or product data',
            ];
        }

        // Calculate expected commission using margin-based logic
        $salePrice = $article->prix_unitaire;
        $costPrice = $product->prix_achat;
        $recommendedPrice = $product->prix_vente;
        $fixedCommission = $product->prix_affilie;
        $quantity = $article->quantite;

        $expectedAmount = 0;
        $expectedRule = '';

        if (abs($salePrice - $recommendedPrice) < 0.01 && $fixedCommission && $fixedCommission > 0) {
            $expectedAmount = round($fixedCommission * $quantity, 2);
            $expectedRule = 'FIXED_COMMISSION';
        } else {
            $marginPerUnit = max(0, $salePrice - $costPrice);
            $expectedAmount = round($marginPerUnit * $quantity, 2);
            $expectedRule = abs($salePrice - $recommendedPrice) < 0.01 ? 'RECOMMENDED_MARGIN' : 'MODIFIED_MARGIN';
        }

        $actualAmount = $commission->amount;
        $isCorrect = abs($expectedAmount - $actualAmount) < 0.01;

        return [
            'commission_id' => $commission->id,
            'order_id' => $commission->commande_id,
            'product_title' => $product->titre,
            'cost_price' => $costPrice,
            'recommended_price' => $recommendedPrice,
            'fixed_commission' => $fixedCommission,
            'sale_price' => $salePrice,
            'quantity' => $quantity,
            'expected_amount' => $expectedAmount,
            'actual_amount' => $actualAmount,
            'difference' => round($expectedAmount - $actualAmount, 2),
            'expected_rule' => $expectedRule,
            'actual_rule' => $commission->rule_code,
            'is_correct' => $isCorrect,
        ];
    }
}
