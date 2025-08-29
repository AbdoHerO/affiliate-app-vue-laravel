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
        $totalProductCommission = 0;

        DB::transaction(function () use ($order, $affiliate, &$results, &$totalProductCommission) {
            // First, calculate commission per order item (without delivery fees)
            foreach ($order->articles as $article) {
                $commission = $this->calculateForOrderItem($order, $article, $affiliate);
                if ($commission) {
                    $results[] = $commission;
                    $totalProductCommission += $commission->amount;
                }
            }

            // Calculate delivery fee (total_ttc - sum of article totals)
            $totalArticles = $order->articles->sum('total_ligne');
            $deliveryFee = $order->total_ttc - $totalArticles;

            // Apply delivery fee deduction to commissions
            if ($deliveryFee > 0 && count($results) > 0) {
                $this->applyDeliveryFeeDeduction($results, $deliveryFee, $order);
            }
        });

        // Recalculate total after delivery fee deduction
        $finalTotalCommission = collect($results)->sum('amount');

        Log::info('Commission calculation completed', [
            'order_id' => $order->id,
            'product_commission' => $totalProductCommission,
            'delivery_fee' => $deliveryFee ?? 0,
            'final_commission' => $finalTotalCommission,
            'items_count' => count($results)
        ]);

        return [
            'success' => true,
            'commissions' => $results,
            'total_amount' => $finalTotalCommission
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
     * Apply delivery fee deduction to commissions
     */
    protected function applyDeliveryFeeDeduction(array &$commissions, float $deliveryFee, Commande $order): void
    {
        if (empty($commissions)) {
            return;
        }

        Log::info('Applying delivery fee deduction', [
            'order_id' => $order->id,
            'order_type' => $order->type_command,
            'delivery_fee' => $deliveryFee,
            'commissions_count' => count($commissions),
            'total_commission_before' => collect($commissions)->sum('amount')
        ]);

        // For exchange orders, the delivery fee should make the commission negative
        if ($order->type_command === 'exchange') {
            // For exchange orders, set commission to negative delivery fee
            // Split the negative amount across all commissions if multiple items
            $negativeAmountPerCommission = -$deliveryFee / count($commissions);

            foreach ($commissions as $commission) {
                $commission->amount = round($negativeAmountPerCommission, 2);
                $commission->notes = "Exchange order: commission = -delivery fee (total: {$deliveryFee} MAD)";
                $commission->rule_code = 'EXCHANGE_NEGATIVE_DELIVERY';
                $commission->save();

                Log::info('Applied exchange negative commission', [
                    'commission_id' => $commission->id,
                    'amount' => $commission->amount,
                    'delivery_fee' => $deliveryFee
                ]);
            }
        } else {
            // For normal orders, distribute the delivery fee deduction proportionally
            $totalCommission = collect($commissions)->sum('amount');

            if ($totalCommission > 0) {
                foreach ($commissions as $commission) {
                    $proportion = $commission->amount / $totalCommission;
                    $deduction = round($deliveryFee * $proportion, 2);
                    $originalAmount = $commission->amount;
                    $commission->amount = round($commission->amount - $deduction, 2);
                    $commission->notes = ($commission->notes ?? '') . " | Delivery fee deduction: -{$deduction} MAD";
                    $commission->save();

                    Log::info('Applied proportional delivery fee deduction', [
                        'commission_id' => $commission->id,
                        'original_amount' => $originalAmount,
                        'deduction' => $deduction,
                        'final_amount' => $commission->amount,
                        'proportion' => $proportion
                    ]);
                }
            } else {
                // If no positive commission, apply full delivery fee to first commission
                $commissions[0]->amount = -$deliveryFee;
                $commissions[0]->notes = "No product commission, full delivery fee deduction: -{$deliveryFee} MAD";
                $commissions[0]->rule_code = 'NEGATIVE_DELIVERY_ONLY';
                $commissions[0]->save();

                Log::info('Applied full delivery fee as negative commission', [
                    'commission_id' => $commissions[0]->id,
                    'amount' => $commissions[0]->amount,
                    'delivery_fee' => $deliveryFee
                ]);
            }
        }

        Log::info('Delivery fee deduction completed', [
            'order_id' => $order->id,
            'total_commission_after' => collect($commissions)->sum('amount')
        ]);
    }

    /**
     * Calculate commission amount using margin-based logic
     */
    protected function calculateCommissionAmount($article, Produit $product, User $affiliate): array
    {
        // Check if this is an exchange order - no commission for exchanges
        $commandType = $article->type_command ?? 'order_sample';

        if ($commandType === 'exchange') {
            return [
                'base_amount' => 0,
                'rate' => 0,
                'amount' => 0,
                'rule_code' => 'EXCHANGE_NO_COMMISSION',
                'notes' => 'No commission for exchange orders',
                'calculation_details' => [
                    'command_type' => $commandType,
                    'rule_applied' => 'EXCHANGE_NO_COMMISSION',
                    'reason' => 'Exchange orders do not generate commission'
                ]
            ];
        }

        // Check feature flag for commission strategy
        $strategy = AppSetting::get('commission.strategy', 'margin');

        if ($strategy === 'margin') {
            return $this->calculateMarginBasedCommission($article, $product, $affiliate);
        }

        // Fallback to legacy calculation for backward compatibility
        return $this->calculateLegacyCommission($article, $product, $affiliate);
    }

    /**
     * Calculate commission using margin-based business rules
     */
    protected function calculateMarginBasedCommission($article, Produit $product, User $affiliate): array
    {
        // Use sell_price if available, otherwise fall back to prix_unitaire
        $salePrice = $article->sell_price ?? $article->prix_unitaire;
        $costPrice = $product->prix_achat ?? 0;
        $recommendedPrice = $product->prix_vente ?? 0;
        $fixedCommission = $product->prix_affilie ?? 0;
        $quantity = $article->quantite ?? 1;

        // Log calculation inputs for audit
        $calculationInputs = [
            'product_id' => $product->id,
            'product_title' => $product->titre,
            'cost_price' => $costPrice,
            'recommended_price' => $recommendedPrice,
            'fixed_commission' => $fixedCommission,
            'sale_price' => $salePrice,
            'quantity' => $quantity,
            'article_type' => $article->type_command ?? 'order_sample',
        ];

        Log::info('Commission calculation inputs', $calculationInputs);

        // Ensure we have valid prices
        if ($salePrice <= 0) {
            Log::warning('Invalid sale price for commission calculation', $calculationInputs);
            return [
                'base_amount' => 0,
                'rate' => null,
                'amount' => 0,
                'rule_code' => 'INVALID_SALE_PRICE',
                'notes' => 'Invalid sale price - cannot calculate commission'
            ];
        }

        // RULE 1: Fixed commission when using recommended price
        if (abs($salePrice - $recommendedPrice) < 0.01 && $fixedCommission > 0) {
            $commissionAmount = round($fixedCommission * $quantity, 2);

            Log::info('Applied FIXED_COMMISSION rule', [
                'calculation' => "{$fixedCommission} × {$quantity} = {$commissionAmount}",
                'commission_amount' => $commissionAmount
            ]);

            return [
                'base_amount' => $fixedCommission * $quantity,
                'rate' => null,
                'amount' => $commissionAmount,
                'rule_code' => 'FIXED_COMMISSION',
                'notes' => 'Fixed commission per item from product settings'
            ];
        }

        // RULE 2: Margin-based calculation (recommended or modified price)
        $marginPerUnit = max(0, $salePrice - $costPrice);
        $commissionAmount = round($marginPerUnit * $quantity, 2);

        $ruleCode = abs($salePrice - $recommendedPrice) < 0.01 ? 'RECOMMENDED_MARGIN' : 'MODIFIED_MARGIN';

        Log::info("Applied {$ruleCode} rule", [
            'calculation' => "max(0, {$salePrice} - {$costPrice}) × {$quantity} = {$commissionAmount}",
            'margin_per_unit' => $marginPerUnit,
            'commission_amount' => $commissionAmount,
            'sale_price' => $salePrice,
            'cost_price' => $costPrice,
            'quantity' => $quantity
        ]);

        return [
            'base_amount' => $marginPerUnit * $quantity,
            'rate' => null,
            'amount' => $commissionAmount,
            'rule_code' => $ruleCode,
            'notes' => $ruleCode === 'RECOMMENDED_MARGIN'
                ? 'Commission based on recommended price margin'
                : 'Commission based on actual sale price margin'
        ];
    }

    /**
     * Legacy commission calculation (for backward compatibility)
     */
    protected function calculateLegacyCommission($article, Produit $product, User $affiliate): array
    {
        $baseAmount = $article->total_ligne;
        $qty = $article->quantite;

        // Check if product has fixed commission per item
        if ($product->prix_affilie && $product->prix_affilie > 0) {
            return [
                'base_amount' => $baseAmount,
                'rate' => null,
                'amount' => $product->prix_affilie * $qty,
                'rule_code' => 'PRODUCT_FIXED_LEGACY',
                'notes' => 'Fixed commission per item from product settings (legacy)'
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
                    'rule_code' => 'RULE_FIXED_LEGACY',
                    'notes' => 'Fixed commission from commission rule (legacy)'
                ];
            } else {
                $rate = $commissionRule->valeur;
                return [
                    'base_amount' => $baseAmount,
                    'rate' => $rate,
                    'amount' => ($baseAmount * $rate) / 100,
                    'rule_code' => 'RULE_PERCENTAGE_LEGACY',
                    'notes' => 'Percentage commission from commission rule (legacy)'
                ];
            }
        }

        // Use default rate from settings
        $defaultRate = AppSetting::get('commission.default_rate', 10.0);
        return [
            'base_amount' => $baseAmount,
            'rate' => $defaultRate,
            'amount' => ($baseAmount * $defaultRate) / 100,
            'rule_code' => 'DEFAULT_RATE_LEGACY',
            'notes' => 'Default commission rate from settings (legacy)'
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
