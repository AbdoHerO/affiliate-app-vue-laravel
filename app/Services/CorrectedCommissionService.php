<?php

namespace App\Services;

use App\Models\User;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\Produit;
use App\Models\CommissionAffilie;
use App\Models\RegleCommission;
use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrected Commission Service implementing proper pricing model
 * 
 * PRICING MODEL:
 * - prix_achat: Wholesale/cost price
 * - prix_vente: Recommended retail price  
 * - prix_affilie: Fixed commission amount (optional)
 * - prix_unitaire: Actual sale price used by affiliate
 * 
 * COMMISSION RULES:
 * 1. If affiliate uses recommended price (prix_vente) AND product has prix_affilie → Commission = prix_affilie * qty
 * 2. If affiliate uses recommended price (prix_vente) AND no prix_affilie → Commission = (prix_vente - prix_achat) * qty
 * 3. If affiliate modifies price → Commission = (sale_price - prix_achat) * qty
 * 4. Commission cannot be negative (sale_price must be >= prix_achat)
 */
class CorrectedCommissionService
{
    /**
     * Calculate commissions for an order using correct pricing model
     */
    public function calculateForOrder(Commande $order): array
    {
        Log::info('Calculating commissions for order (CORRECTED)', ['order_id' => $order->id]);

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

        Log::info('Commission calculation completed (CORRECTED)', [
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
     * Calculate commission for a single order item using correct pricing model
     */
    protected function calculateForOrderItem(Commande $order, CommandeArticle $article, User $affiliate): ?CommissionAffilie
    {
        // Check if commission already exists for this item
        $existingCommission = CommissionAffilie::where('commande_article_id', $article->id)
            ->where('user_id', $affiliate->id)
            ->first();

        if ($existingCommission && $existingCommission->status !== CommissionAffilie::STATUS_PENDING_CALC) {
            Log::info('Commission already calculated for item (CORRECTED)', [
                'commission_id' => $existingCommission->id,
                'status' => $existingCommission->status
            ]);
            return $existingCommission;
        }

        $product = $article->produit;
        if (!$product) {
            Log::warning('Article has no product', ['article_id' => $article->id]);
            return null;
        }

        $commissionData = $this->calculateCommissionAmountCorrected($article, $product, $affiliate);

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
                // Legacy fields for backward compatibility
                'montant' => $commissionData['amount'],
                'statut' => 'valide',
            ]
        );

        Log::info('Commission calculated for item (CORRECTED)', [
            'commission_id' => $commission->id,
            'amount' => $commission->amount,
            'rule_code' => $commission->rule_code,
            'calculation_details' => $commissionData['calculation_details'] ?? null,
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

        // For exchange orders, the delivery fee should make the commission negative
        if ($order->type_command === 'exchange') {
            // For exchange orders, set commission to negative delivery fee
            foreach ($commissions as $commission) {
                $commission->amount = -$deliveryFee;
                $commission->notes = "Exchange order: commission = -delivery fee ({$deliveryFee} MAD)";
                $commission->rule_code = 'EXCHANGE_NEGATIVE_DELIVERY';
                $commission->save();
            }
        } else {
            // For normal orders, distribute the delivery fee deduction proportionally
            $totalCommission = collect($commissions)->sum('amount');

            if ($totalCommission > 0) {
                foreach ($commissions as $commission) {
                    $proportion = $commission->amount / $totalCommission;
                    $deduction = $deliveryFee * $proportion;
                    $commission->amount = $commission->amount - $deduction;
                    $commission->notes = ($commission->notes ?? '') . " | Delivery fee deduction: -{$deduction} MAD";
                    $commission->save();
                }
            } else {
                // If no positive commission, apply full delivery fee to first commission
                $commissions[0]->amount = -$deliveryFee;
                $commissions[0]->notes = "No product commission, full delivery fee deduction: -{$deliveryFee} MAD";
                $commissions[0]->save();
            }
        }
    }

    /**
     * Calculate commission amount using CORRECT pricing model
     */
    public function calculateCommissionAmountCorrected(CommandeArticle $article, Produit $product, User $affiliate): array
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

        // Use sell_price if available, otherwise fall back to prix_unitaire
        $salePrice = $article->sell_price ?? $article->prix_unitaire;
        $costPrice = $product->prix_achat;
        $recommendedPrice = $product->prix_vente;
        $fixedCommission = $product->prix_affilie;
        $quantity = $article->quantite;

        // Detailed calculation logging
        $calculationDetails = [
            'product_id' => $product->id,
            'product_title' => $product->titre,
            'cost_price' => $costPrice,
            'recommended_price' => $recommendedPrice,
            'fixed_commission' => $fixedCommission,
            'sale_price' => $salePrice,
            'quantity' => $quantity,
        ];

        // RULE 1: Recommended price with fixed commission
        if ($salePrice == $recommendedPrice && $fixedCommission && $fixedCommission > 0) {
            $commissionAmount = $fixedCommission * $quantity;
            return [
                'base_amount' => $fixedCommission,
                'rate' => null,
                'amount' => $commissionAmount,
                'rule_code' => 'FIXED_COMMISSION',
                'notes' => 'Fixed commission per item from product settings',
                'calculation_details' => array_merge($calculationDetails, [
                    'rule_applied' => 'FIXED_COMMISSION',
                    'calculation' => "{$fixedCommission} × {$quantity} = {$commissionAmount}",
                ]),
            ];
        }

        // RULE 2: Recommended price without fixed commission (use margin)
        if ($salePrice == $recommendedPrice) {
            $marginPerUnit = max(0, $recommendedPrice - $costPrice);
            $commissionAmount = $marginPerUnit * $quantity;
            return [
                'base_amount' => $marginPerUnit * $quantity,
                'rate' => null,
                'amount' => $commissionAmount,
                'rule_code' => 'RECOMMENDED_MARGIN',
                'notes' => 'Commission based on recommended price margin',
                'calculation_details' => array_merge($calculationDetails, [
                    'rule_applied' => 'RECOMMENDED_MARGIN',
                    'margin_per_unit' => $marginPerUnit,
                    'calculation' => "({$recommendedPrice} - {$costPrice}) × {$quantity} = {$commissionAmount}",
                ]),
            ];
        }

        // RULE 3: Modified price (use actual margin)
        $marginPerUnit = max(0, $salePrice - $costPrice);
        $commissionAmount = $marginPerUnit * $quantity;
        
        return [
            'base_amount' => $marginPerUnit * $quantity,
            'rate' => null,
            'amount' => $commissionAmount,
            'rule_code' => 'MODIFIED_MARGIN',
            'notes' => 'Commission based on actual sale price margin',
            'calculation_details' => array_merge($calculationDetails, [
                'rule_applied' => 'MODIFIED_MARGIN',
                'margin_per_unit' => $marginPerUnit,
                'calculation' => "({$salePrice} - {$costPrice}) × {$quantity} = {$commissionAmount}",
            ]),
        ];
    }

    /**
     * Calculate eligibility date for commission
     */
    protected function calculateEligibilityDate(Commande $order): \DateTime
    {
        // Commission becomes eligible immediately upon delivery
        return now();
    }

    /**
     * Recalculate commissions for an order using corrected logic
     */
    public function recalculate(Commande $order): array
    {
        Log::info('Recalculating commissions for order (CORRECTED)', ['order_id' => $order->id]);

        // Mark existing commissions as pending recalculation
        CommissionAffilie::where('commande_id', $order->id)
            ->whereNotIn('status', [CommissionAffilie::STATUS_PAID])
            ->update(['status' => CommissionAffilie::STATUS_PENDING_CALC]);

        return $this->calculateForOrder($order);
    }

    /**
     * Validate commission calculation against expected pricing model
     */
    public function validateCalculation(CommandeArticle $article, CommissionAffilie $commission): array
    {
        $product = $article->produit;
        if (!$product) {
            return ['valid' => false, 'error' => 'Product not found'];
        }

        $expectedData = $this->calculateCommissionAmountCorrected($article, $product, $commission->user);
        $expectedAmount = $expectedData['amount'];
        $actualAmount = $commission->amount;

        $isValid = abs($expectedAmount - $actualAmount) < 0.01;

        return [
            'valid' => $isValid,
            'expected_amount' => $expectedAmount,
            'actual_amount' => $actualAmount,
            'difference' => abs($expectedAmount - $actualAmount),
            'expected_rule' => $expectedData['rule_code'],
            'actual_rule' => $commission->rule_code,
            'calculation_details' => $expectedData['calculation_details'] ?? null,
        ];
    }
}
