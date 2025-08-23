<?php

namespace App\Jobs;

use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\CommissionAffilie;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * Commission Backfill Job
 * 
 * Safely recalculates historical commissions using margin-based logic
 * Creates adjustment entries without modifying original commission records
 */
class CommissionBackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private bool $dryRun;
    private int $chunkSize;
    private string $batchId;
    private array $metrics;

    public function __construct(bool $dryRun = true, int $chunkSize = 100)
    {
        $this->dryRun = $dryRun;
        $this->chunkSize = $chunkSize;
        $this->batchId = 'backfill_' . now()->format('Y_m_d_H_i_s');
        $this->metrics = [
            'examined' => 0,
            'adjustments_needed' => 0,
            'adjustments_created' => 0,
            'total_delta' => 0.0,
            'errors' => 0,
        ];
    }

    public function handle(): void
    {
        $startTime = now();
        
        Log::info('Starting commission backfill', [
            'batch_id' => $this->batchId,
            'dry_run' => $this->dryRun,
            'chunk_size' => $this->chunkSize,
        ]);

        try {
            $this->processCommissions();
            $this->generateReport();
            
            Log::info('Commission backfill completed successfully', [
                'batch_id' => $this->batchId,
                'duration' => $startTime->diffInSeconds(now()),
                'metrics' => $this->metrics,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Commission backfill failed', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function processCommissions(): void
    {
        $commissionService = new CommissionService();
        $csvData = [];
        $csvHeaders = [
            'affiliate_id',
            'affiliate_email',
            'order_id',
            'article_id',
            'product_id',
            'product_title',
            'cost_price',
            'recommended_price',
            'fixed_commission',
            'sale_price',
            'quantity',
            'current_commission',
            'expected_commission',
            'delta',
            'rule_applied',
            'needs_adjustment',
            'already_paid',
            'action_taken',
        ];

        // Get all delivered orders with existing commissions
        $query = Commande::where('statut', 'livree')
            ->whereHas('commissions')
            ->with(['articles.produit', 'commissions', 'affiliate']);

        $query->chunk($this->chunkSize, function ($orders) use ($commissionService, &$csvData) {
            foreach ($orders as $order) {
                $this->processOrder($order, $commissionService, $csvData);
            }
        });

        // Write CSV report
        $this->writeCsvReport($csvHeaders, $csvData);
    }

    private function processOrder(Commande $order, CommissionService $commissionService, array &$csvData): void
    {
        if (!$order->affiliate || !$order->affiliate->hasRole('affiliate')) {
            return;
        }

        foreach ($order->articles as $article) {
            $this->processArticle($order, $article, $commissionService, $csvData);
        }
    }

    private function processArticle(Commande $order, CommandeArticle $article, CommissionService $commissionService, array &$csvData): void
    {
        $this->metrics['examined']++;

        $product = $article->produit;
        if (!$product) {
            Log::warning('Article has no product', ['article_id' => $article->id]);
            $this->metrics['errors']++;
            return;
        }

        // Find existing commission
        $existingCommission = $order->commissions->where('commande_article_id', $article->id)->first();
        if (!$existingCommission) {
            Log::warning('No commission found for article', ['article_id' => $article->id]);
            return;
        }

        // Calculate expected commission using margin-based logic
        $expectedData = $this->calculateExpectedCommission($article, $product, $order->affiliate);
        $expectedAmount = $expectedData['amount'];
        $currentAmount = $existingCommission->amount;
        $delta = round($expectedAmount - $currentAmount, 2);

        $needsAdjustment = abs($delta) >= 0.01;
        $alreadyPaid = !is_null($existingCommission->paid_withdrawal_id);

        $actionTaken = 'none';
        if ($needsAdjustment) {
            $this->metrics['adjustments_needed']++;
            $this->metrics['total_delta'] += $delta;

            if (!$this->dryRun) {
                $actionTaken = $this->createAdjustment($existingCommission, $delta, $expectedData);
                if ($actionTaken === 'adjustment_created') {
                    $this->metrics['adjustments_created']++;
                }
            } else {
                $actionTaken = 'dry_run_only';
            }
        }

        // Add to CSV data
        $csvData[] = [
            'affiliate_id' => $order->affiliate->id,
            'affiliate_email' => $order->affiliate->email,
            'order_id' => $order->id,
            'article_id' => $article->id,
            'product_id' => $product->id,
            'product_title' => $product->titre,
            'cost_price' => $product->prix_achat,
            'recommended_price' => $product->prix_vente,
            'fixed_commission' => $product->prix_affilie,
            'sale_price' => $article->prix_unitaire,
            'quantity' => $article->quantite,
            'current_commission' => $currentAmount,
            'expected_commission' => $expectedAmount,
            'delta' => $delta,
            'rule_applied' => $expectedData['rule_code'],
            'needs_adjustment' => $needsAdjustment ? 'yes' : 'no',
            'already_paid' => $alreadyPaid ? 'yes' : 'no',
            'action_taken' => $actionTaken,
        ];
    }

    private function calculateExpectedCommission(CommandeArticle $article, $product, User $affiliate): array
    {
        $salePrice = $article->prix_unitaire;
        $costPrice = $product->prix_achat;
        $recommendedPrice = $product->prix_vente;
        $fixedCommission = $product->prix_affilie;
        $quantity = $article->quantite;

        // Apply margin-based business rules
        if (abs($salePrice - $recommendedPrice) < 0.01 && $fixedCommission && $fixedCommission > 0) {
            // Fixed commission rule
            return [
                'amount' => round($fixedCommission * $quantity, 2),
                'rule_code' => 'FIXED_COMMISSION',
                'base_amount' => $fixedCommission * $quantity,
            ];
        } else {
            // Margin-based rule
            $marginPerUnit = max(0, $salePrice - $costPrice);
            $ruleCode = abs($salePrice - $recommendedPrice) < 0.01 ? 'RECOMMENDED_MARGIN' : 'MODIFIED_MARGIN';
            
            return [
                'amount' => round($marginPerUnit * $quantity, 2),
                'rule_code' => $ruleCode,
                'base_amount' => $marginPerUnit * $quantity,
            ];
        }
    }

    private function createAdjustment(CommissionAffilie $originalCommission, float $delta, array $expectedData): string
    {
        try {
            // Check if adjustment already exists (idempotency)
            $existingAdjustment = CommissionAffilie::where('commande_article_id', $originalCommission->commande_article_id)
                ->where('type', 'adjustment')
                ->where('notes', 'LIKE', '%margin_backfill%')
                ->first();

            if ($existingAdjustment) {
                Log::info('Adjustment already exists', [
                    'original_commission_id' => $originalCommission->id,
                    'existing_adjustment_id' => $existingAdjustment->id,
                ]);
                return 'adjustment_exists';
            }

            // Create adjustment commission
            $adjustment = CommissionAffilie::create([
                'commande_article_id' => $originalCommission->commande_article_id,
                'commande_id' => $originalCommission->commande_id,
                'user_id' => $originalCommission->user_id,
                'affilie_id' => $originalCommission->affilie_id,
                'type' => 'adjustment',
                'base_amount' => $expectedData['base_amount'],
                'rate' => null,
                'qty' => $originalCommission->qty,
                'amount' => $delta,
                'currency' => $originalCommission->currency,
                'status' => 'calculated',
                'rule_code' => $expectedData['rule_code'] . '_BACKFILL',
                'eligible_at' => now(),
                'notes' => "Margin backfill adjustment. Original: {$originalCommission->amount}, Expected: {$expectedData['amount']}, Delta: {$delta}. Batch: {$this->batchId}",
                // Legacy fields
                'montant' => $delta,
                'statut' => 'valide',
                'motif' => 'Commission backfill adjustment',
            ]);

            Log::info('Created commission adjustment', [
                'original_commission_id' => $originalCommission->id,
                'adjustment_id' => $adjustment->id,
                'delta' => $delta,
                'batch_id' => $this->batchId,
            ]);

            return 'adjustment_created';

        } catch (\Exception $e) {
            Log::error('Failed to create adjustment', [
                'original_commission_id' => $originalCommission->id,
                'delta' => $delta,
                'error' => $e->getMessage(),
            ]);
            $this->metrics['errors']++;
            return 'error';
        }
    }

    private function writeCsvReport(array $headers, array $data): void
    {
        $filename = "commission_backfill_{$this->batchId}.csv";
        $filepath = "commission_backfills/{$filename}";

        $csvContent = implode(',', $headers) . "\n";
        foreach ($data as $row) {
            $csvContent .= implode(',', array_map(function ($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row)) . "\n";
        }

        Storage::disk('local')->put($filepath, $csvContent);

        Log::info('CSV report generated', [
            'batch_id' => $this->batchId,
            'filepath' => $filepath,
            'rows' => count($data),
        ]);
    }

    private function generateReport(): void
    {
        $report = [
            'batch_id' => $this->batchId,
            'dry_run' => $this->dryRun,
            'timestamp' => now()->toISOString(),
            'metrics' => $this->metrics,
            'summary' => [
                'accuracy_rate' => $this->metrics['examined'] > 0 
                    ? round((($this->metrics['examined'] - $this->metrics['adjustments_needed']) / $this->metrics['examined']) * 100, 2)
                    : 100,
                'average_delta' => $this->metrics['adjustments_needed'] > 0 
                    ? round($this->metrics['total_delta'] / $this->metrics['adjustments_needed'], 2)
                    : 0,
            ],
        ];

        $reportPath = "commission_backfills/report_{$this->batchId}.json";
        Storage::disk('local')->put($reportPath, json_encode($report, JSON_PRETTY_PRINT));

        Log::info('Backfill report generated', $report);
    }
}
