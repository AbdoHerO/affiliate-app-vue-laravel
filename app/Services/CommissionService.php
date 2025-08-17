<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Models\Produit;
use App\Models\RegleCommission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Calculate commissions for an order
     */
    public function calculateForOrder(Commande $order): array
    {
        Log::info('Calculating commissions for order', ['order_id' => $order->id]);

        if (!$order->user_id) {
            Log::warning('Order has no affiliate user', ['order_id' => $order->id]);
            return ['success' => false, 'message' => 'Order has no affiliate user'];
        }

        $affiliate = User::find($order->user_id);
        if (!$affiliate || !$affiliate->hasRole('affiliate')) {
            Log::warning('Invalid affiliate for order', ['order_id' => $order->id, 'user_id' => $order->user_id]);
            return ['success' => false, 'message' => 'Invalid affiliate for order'];
        }

        $results = [];
        $totalCommission = 0;

        DB::transaction(function () use ($order, $affiliate, &$results, &$totalCommission) {
            // Calculate commission per order item
            foreach ($order->articles as $article) {
                $commission = $this->calculateForOrderItem($order, $article, $affiliate);
                if ($commission) {
                    $results[] = $commission;
                    $totalCommission += $commission->amount;
                }
            }
        });

        Log::info('Commission calculation completed', [
            'order_id' => $order->id,
            'total_commission' => $totalCommission,
            'items_count' => count($results)
        ]);

        return [
            'success' => true,
            'commissions' => $results,
            'total_amount' => $totalCommission
        ];
    }

    /**
     * Calculate commission for a single order item
     */
    protected function calculateForOrderItem(Commande $order, $article, User $affiliate): ?CommissionAffilie
    {
        // Check if commission already exists for this item
        $existingCommission = CommissionAffilie::where('commande_article_id', $article->id)
            ->where('user_id', $affiliate->id)
            ->first();

        if ($existingCommission && $existingCommission->status !== CommissionAffilie::STATUS_PENDING_CALC) {
            Log::info('Commission already calculated for item', [
                'commission_id' => $existingCommission->id,
                'status' => $existingCommission->status
            ]);
            return $existingCommission;
        }

        $product = $article->produit;
        $commissionData = $this->calculateCommissionAmount($article, $product, $affiliate);

        $commission = CommissionAffilie::updateOrCreate(
            [
                'commande_article_id' => $article->id,
                'user_id' => $affiliate->id,
            ],
            [
                'commande_id' => $order->id,
                'affilie_id' => $affiliate->profilAffilie?->id, // Legacy compatibility
                'type' => CommissionAffilie::TYPE_SALE,
                'base_amount' => $commissionData['base_amount'],
                'rate' => $commissionData['rate'],
                'qty' => $article->quantite,
                'amount' => $commissionData['amount'],
                'currency' => $order->devise ?? 'MAD',
                'status' => CommissionAffilie::STATUS_CALCULATED,
                'rule_code' => $commissionData['rule_code'],
                'eligible_at' => $this->calculateEligibilityDate($order),
                'notes' => $commissionData['notes'] ?? null,
            ]
        );

        Log::info('Commission calculated for item', [
            'commission_id' => $commission->id,
            'amount' => $commission->amount,
            'rule_code' => $commission->rule_code
        ]);

        return $commission;
    }

    /**
     * Calculate commission amount based on product and affiliate
     */
    protected function calculateCommissionAmount($article, Produit $product, User $affiliate): array
    {
        $baseAmount = $article->total_ligne;
        $qty = $article->quantite;

        // Check if product has fixed commission per item
        if ($product->prix_affilie && $product->prix_affilie > 0) {
            return [
                'base_amount' => $baseAmount,
                'rate' => null,
                'amount' => $product->prix_affilie * $qty,
                'rule_code' => 'PRODUCT_FIXED',
                'notes' => 'Fixed commission per item from product settings'
            ];
        }

        // Try to find commission rule from offers/rules
        $commissionRule = $this->findCommissionRule($product, $affiliate);
        if ($commissionRule) {
            if ($commissionRule->type === 'fixe') {
                return [
                    'base_amount' => $baseAmount,
                    'rate' => null,
                    'amount' => $commissionRule->valeur * $qty,
                    'rule_code' => 'RULE_FIXED',
                    'notes' => 'Fixed commission from commission rule'
                ];
            } else {
                $rate = $commissionRule->valeur;
                return [
                    'base_amount' => $baseAmount,
                    'rate' => $rate,
                    'amount' => ($baseAmount * $rate) / 100,
                    'rule_code' => 'RULE_PERCENTAGE',
                    'notes' => 'Percentage commission from commission rule'
                ];
            }
        }

        // Use default rate from settings
        $defaultRate = AppSetting::get('commission.default_rate', 10.0);
        return [
            'base_amount' => $baseAmount,
            'rate' => $defaultRate,
            'amount' => ($baseAmount * $defaultRate) / 100,
            'rule_code' => 'DEFAULT_RATE',
            'notes' => 'Default commission rate from settings'
        ];
    }

    /**
     * Find applicable commission rule for product and affiliate
     */
    protected function findCommissionRule(Produit $product, User $affiliate): ?RegleCommission
    {
        // This is a simplified version - in a full implementation,
        // you would check offers, affiliate tier, country, etc.
        return null;
    }

    /**
     * Calculate when commission becomes eligible for payout
     */
    protected function calculateEligibilityDate(Commande $order): ?\Carbon\Carbon
    {
        $triggerStatus = AppSetting::get('commission.trigger_status', 'livree');
        $cooldownDays = AppSetting::get('commission.cooldown_days', 7);

        if ($order->statut === $triggerStatus) {
            return now()->addDays($cooldownDays);
        }

        // If order is not yet at trigger status, calculate from when it might be
        return now()->addDays($cooldownDays + 3); // Add buffer for delivery
    }

    /**
     * Recalculate commissions for an order (idempotent)
     */
    public function recalculate(Commande $order): array
    {
        Log::info('Recalculating commissions for order', ['order_id' => $order->id]);

        // Mark existing commissions as pending recalculation
        CommissionAffilie::where('commande_id', $order->id)
            ->whereNotIn('status', [CommissionAffilie::STATUS_PAID])
            ->update(['status' => CommissionAffilie::STATUS_PENDING_CALC]);

        return $this->calculateForOrder($order);
    }

    /**
     * Apply return policy when order is returned/canceled
     */
    public function applyReturnPolicy(Commande $order): array
    {
        Log::info('Applying return policy for order', ['order_id' => $order->id]);

        $returnPolicy = AppSetting::get('commission.return_policy', 'zero_on_return');
        $results = [];

        DB::transaction(function () use ($order, $returnPolicy, &$results) {
            $commissions = CommissionAffilie::where('commande_id', $order->id)
                ->whereNotIn('status', [CommissionAffilie::STATUS_PAID])
                ->get();

            foreach ($commissions as $commission) {
                if ($returnPolicy === 'zero_on_return') {
                    $commission->update([
                        'status' => CommissionAffilie::STATUS_CANCELED,
                        'notes' => ($commission->notes ?? '') . "\nCanceled due to order return/cancellation"
                    ]);
                    $results[] = $commission;
                }
            }
        });

        Log::info('Return policy applied', [
            'order_id' => $order->id,
            'affected_commissions' => count($results)
        ]);

        return ['success' => true, 'affected_commissions' => $results];
    }

    /**
     * Move eligible commissions to eligible status
     */
    public function processEligibleCommissions(): int
    {
        $count = 0;

        CommissionAffilie::readyForEligibility()->chunk(100, function ($commissions) use (&$count) {
            foreach ($commissions as $commission) {
                $commission->update(['status' => CommissionAffilie::STATUS_ELIGIBLE]);
                $count++;
            }
        });

        Log::info('Processed eligible commissions', ['count' => $count]);
        return $count;
    }
}
