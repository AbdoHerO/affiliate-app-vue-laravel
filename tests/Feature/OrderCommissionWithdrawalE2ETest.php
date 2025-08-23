<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\Client;
use App\Models\Adresse;
use App\Models\ShippingParcel;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Models\Produit;
use App\Models\Categorie;
use App\Events\OrderDelivered;
use App\Services\CommissionService;
use Illuminate\Support\Facades\Log;

/**
 * Comprehensive E2E Test Suite for Orders → Shipping → Delivery → Commissions → Withdrawals
 *
 * Tests the complete order lifecycle for affiliate 0198cd28-0b1f-7170-a26f-61e13ab21d72
 * covering recommended and modified pricing scenarios with detailed pricing model validation.
 *
 * PRICING MODEL VALIDATION:
 * - prix_achat: Wholesale/cost price (e.g., 100 MAD)
 * - prix_vente: Recommended retail price (e.g., 150 MAD)
 * - prix_affilie: Fixed commission amount (optional, e.g., 50 MAD)
 * - prix_unitaire: Actual sale price used by affiliate
 *
 * COMMISSION RULES:
 * 1. If affiliate uses recommended price (prix_vente) → Commission = prix_affilie OR (prix_vente - prix_achat)
 * 2. If affiliate modifies price → Commission = (sale_price - prix_achat)
 * 3. Commission cannot be negative (sale_price must be >= prix_achat)
 */
class OrderCommissionWithdrawalE2ETest extends TestCase
{
    private User $testAffiliate;
    private array $testProducts;
    private CommissionService $commissionService;

    protected function setUp(): void
    {
        parent::setUp();

        // Don't fake events - we want real event handling for E2E tests

        // Create test affiliate and products
        $this->testAffiliate = $this->createTestAffiliate('0198cd28-0b1f-7170-a26f-61e13ab21d72');
        $this->testProducts = $this->createTestProducts();

        // Initialize services
        $this->commissionService = app(CommissionService::class);

        Log::info('E2E Test Setup Complete', [
            'affiliate_id' => $this->testAffiliate->id,
            'products_count' => count($this->testProducts),
        ]);
    }

    /**
     * Test Case 1: Order with Recommended Price → Local Shipping → Delivered → Commission
     */
    public function test_order_recommended_price_local_shipping_commission_flow(): void
    {
        Log::info('Starting Test Case 1: Recommended Price + Local Shipping');
        
        // Step 1: Create pre-order with recommended price
        $order = $this->createPreOrder([
            'product' => $this->testProducts['product1'],
            'quantity' => 2,
            'use_recommended_price' => true,
        ]);
        
        $this->assertDatabaseHas('commandes', [
            'id' => $order->id,
            'user_id' => $this->testAffiliate->id,
            'statut' => 'pending',
        ]);
        
        // Step 2: Confirm order
        $this->confirmOrder($order);
        $order->refresh();
        $this->assertEquals('confirmed', $order->statut);
        
        // Step 3: Create local shipping (manual)
        $shippingParcel = $this->createLocalShipping($order);
        $this->assertFalse($shippingParcel->sent_to_carrier);
        
        // Step 4: Update status to delivered
        $this->updateShippingStatus($shippingParcel, 'livree');
        
        // Step 5: Verify commission creation
        $commissions = CommissionAffilie::where('commande_id', $order->id)->get();
        $this->assertCount(1, $commissions);
        
        $commission = $commissions->first();
        $expectedBaseAmount = $this->testProducts['product1']->prix_recommande * 2; // 150 * 2 = 300
        $expectedCommission = $expectedBaseAmount * 0.15; // 15% = 45
        
        $this->assertEquals($expectedBaseAmount, $commission->base_amount);
        $this->assertEquals(0.15, $commission->rate);
        $this->assertEquals($expectedCommission, $commission->amount);
        $this->assertEquals(CommissionAffilie::STATUS_CALCULATED, $commission->status);
        
        // Step 6: Test idempotency - deliver again should not create duplicate
        $this->updateShippingStatus($shippingParcel, 'livree');
        $commissionsAfterDuplicate = CommissionAffilie::where('commande_id', $order->id)->count();
        $this->assertEquals(1, $commissionsAfterDuplicate);
        
        Log::info('Test Case 1 Completed Successfully', [
            'order_id' => $order->id,
            'commission_amount' => $commission->amount,
            'base_amount' => $commission->base_amount,
        ]);
    }

    /**
     * Test Case 2: Order with Modified Price → Carrier Shipping → Delivered via Webhook
     */
    public function test_order_modified_price_carrier_shipping_webhook_flow(): void
    {
        Log::info('Starting Test Case 2: Modified Price + Carrier Shipping');
        
        // Step 1: Create pre-order with modified prices (higher and lower than recommended)
        $order = $this->createPreOrder([
            'product' => $this->testProducts['product2'],
            'quantity' => 1,
            'use_recommended_price' => false,
            'custom_price' => 140.00, // Higher than recommended (120)
        ]);
        
        // Add second line with lower price
        $this->addOrderLine($order, [
            'product' => $this->testProducts['product2'],
            'quantity' => 1,
            'custom_price' => 100.00, // Lower than recommended (120)
        ]);
        
        // Step 2: Confirm order
        $this->confirmOrder($order);
        
        // Step 3: Create carrier shipping
        $shippingParcel = $this->createCarrierShipping($order);
        $this->assertTrue($shippingParcel->sent_to_carrier);
        
        // Step 4: Simulate carrier webhook sequence
        $this->simulateCarrierWebhook($shippingParcel, 'picked_up');
        $this->simulateCarrierWebhook($shippingParcel, 'out_for_delivery');
        $this->simulateCarrierWebhook($shippingParcel, 'delivered');
        
        // Step 5: Verify commission calculations for both lines
        $commissions = CommissionAffilie::where('commande_id', $order->id)->get();
        $this->assertCount(2, $commissions);
        
        // Higher price commission
        $higherPriceCommission = $commissions->where('base_amount', 140.00)->first();
        $this->assertNotNull($higherPriceCommission);
        $this->assertEquals(140.00 * 0.15, $higherPriceCommission->amount); // 21.00
        
        // Lower price commission
        $lowerPriceCommission = $commissions->where('base_amount', 100.00)->first();
        $this->assertNotNull($lowerPriceCommission);
        $this->assertEquals(100.00 * 0.15, $lowerPriceCommission->amount); // 15.00
        
        // Step 6: Test webhook idempotency
        $this->simulateCarrierWebhook($shippingParcel, 'delivered');
        $commissionsAfterDuplicate = CommissionAffilie::where('commande_id', $order->id)->count();
        $this->assertEquals(2, $commissionsAfterDuplicate);
        
        Log::info('Test Case 2 Completed Successfully', [
            'order_id' => $order->id,
            'total_commission' => $commissions->sum('amount'),
            'lines_count' => $commissions->count(),
        ]);
    }

    /**
     * Test Case 3: Withdrawal Request on Eligible Commissions
     */
    public function test_withdrawal_request_eligible_commissions(): void
    {
        Log::info('Starting Test Case 3: Withdrawal Request');
        
        // Setup: Create delivered orders with commissions
        $this->test_order_recommended_price_local_shipping_commission_flow();
        $this->test_order_modified_price_carrier_shipping_webhook_flow();
        
        // Make commissions eligible
        CommissionAffilie::where('user_id', $this->testAffiliate->id)
            ->update(['status' => CommissionAffilie::STATUS_ELIGIBLE]);
        
        // Step 1: Get eligible commissions
        $eligibleCommissions = CommissionAffilie::where('user_id', $this->testAffiliate->id)
            ->where('status', CommissionAffilie::STATUS_ELIGIBLE)
            ->get();
        
        $this->assertGreaterThan(0, $eligibleCommissions->count());
        $totalEligibleAmount = $eligibleCommissions->sum('amount');
        
        // Step 2: Request withdrawal
        $response = $this->apiAs($this->testAffiliate, 'POST', '/api/affiliate/withdrawals/request', [
            'amount' => $totalEligibleAmount,
            'method' => 'bank_transfer',
            'notes' => 'Test withdrawal request',
        ]);
        
        $response->assertStatus(201);
        $withdrawalData = $response->json('data');
        
        // Step 3: Verify withdrawal creation
        $withdrawal = Withdrawal::find($withdrawalData['id']);
        $this->assertNotNull($withdrawal);
        $this->assertEquals($this->testAffiliate->id, $withdrawal->user_id);
        $this->assertEquals($totalEligibleAmount, $withdrawal->amount);
        $this->assertEquals(Withdrawal::STATUS_PENDING, $withdrawal->status);
        
        // Step 4: Verify commissions are linked
        $linkedCommissions = $withdrawal->items()->with('commission')->get();
        $this->assertEquals($eligibleCommissions->count(), $linkedCommissions->count());
        
        $linkedTotal = $linkedCommissions->sum(function ($item) {
            return $item->commission->amount;
        });
        $this->assertEquals($totalEligibleAmount, $linkedTotal);
        
        // Step 5: Test PDF generation
        $pdfResponse = $this->apiAs($this->testAffiliate, 'GET', "/api/affiliate/withdrawals/{$withdrawal->id}/pdf");
        
        if ($withdrawal->status === Withdrawal::STATUS_APPROVED) {
            $pdfResponse->assertStatus(200);
            $this->assertEquals('application/pdf', $pdfResponse->headers->get('Content-Type'));
        } else {
            $pdfResponse->assertStatus(422); // Pending withdrawals can't generate PDF
        }
        
        Log::info('Test Case 3 Completed Successfully', [
            'withdrawal_id' => $withdrawal->id,
            'total_amount' => $withdrawal->amount,
            'commissions_count' => $linkedCommissions->count(),
        ]);
    }

    /**
     * Test Case 4: Permissions and Ownership
     */
    public function test_permissions_and_ownership(): void
    {
        Log::info('Starting Test Case 4: Permissions and Ownership');
        
        // Create another affiliate
        $otherAffiliate = $this->createTestAffiliate();
        
        // Create order for other affiliate
        $otherOrder = $this->createPreOrder([
            'product' => $this->testProducts['product1'],
            'quantity' => 1,
            'use_recommended_price' => true,
            'affiliate' => $otherAffiliate,
        ]);
        
        // Test: Try to access other affiliate's order
        $response = $this->apiAs($this->testAffiliate, 'GET', "/api/affiliate/orders/{$otherOrder->id}");
        $response->assertStatus(404); // Should not find order
        
        // Test: Try to access other affiliate's commissions
        $response = $this->apiAs($this->testAffiliate, 'GET', '/api/affiliate/commissions');
        $response->assertStatus(200);
        
        $commissions = $response->json('data');
        foreach ($commissions as $commission) {
            $this->assertEquals($this->testAffiliate->id, $commission['user_id']);
        }
        
        Log::info('Test Case 4 Completed Successfully');
    }

    /**
     * Test Case 5: Resilience and Error Handling
     */
    public function test_resilience_and_error_handling(): void
    {
        Log::info('Starting Test Case 5: Resilience and Error Handling');
        
        $order = $this->createPreOrder([
            'product' => $this->testProducts['product1'],
            'quantity' => 1,
            'use_recommended_price' => true,
        ]);
        
        $this->confirmOrder($order);
        $shippingParcel = $this->createLocalShipping($order);
        
        // Test: Illegal status transition (delivered → pending)
        $this->updateShippingStatus($shippingParcel, 'livree');
        
        $response = $this->apiAs($this->testAffiliate, 'PATCH', "/api/admin/shipping-orders/{$order->id}/status", [
            'status' => 'pending',
            'note' => 'Trying illegal transition',
        ]);
        
        // Should reject illegal transition
        $response->assertStatus(422);
        
        Log::info('Test Case 5 Completed Successfully');
    }

    /**
     * Test Case 6: Comprehensive Pricing Model Validation
     */
    public function test_pricing_model_validation(): void
    {
        Log::info('Starting Test Case 6: Pricing Model Validation');

        // Create products with specific pricing for validation
        $testProducts = $this->createPricingTestProducts();

        // Test Case 6A: Recommended Price Scenario
        $this->validateRecommendedPriceScenario($testProducts['recommended']);

        // Test Case 6B: Modified Price Higher Scenario
        $this->validateModifiedPriceHigherScenario($testProducts['modified']);

        // Test Case 6C: Modified Price Lower Scenario
        $this->validateModifiedPriceLowerScenario($testProducts['modified']);

        // Test Case 6D: Edge Case - Price at Cost
        $this->validatePriceAtCostScenario($testProducts['edge']);

        Log::info('Test Case 6 Completed Successfully');
    }

    /**
     * Test Case 7: Commission Calculation Audit Trail
     */
    public function test_commission_calculation_audit_trail(): void
    {
        Log::info('Starting Test Case 7: Commission Calculation Audit Trail');

        // Create order with detailed logging
        $product = $this->testProducts['product1'];
        $order = $this->createPreOrder([
            'product' => $product,
            'quantity' => 1,
            'use_recommended_price' => true,
        ]);

        $this->confirmOrder($order);
        $shippingParcel = $this->createLocalShipping($order);

        // Capture commission calculation details before delivery
        $beforeCommissions = CommissionAffilie::where('commande_id', $order->id)->count();

        // Trigger delivery and commission calculation
        $this->updateShippingStatus($shippingParcel, 'livree');

        // Verify commission was created
        $afterCommissions = CommissionAffilie::where('commande_id', $order->id)->count();
        $this->assertEquals($beforeCommissions + 1, $afterCommissions);

        // Get the created commission for detailed validation
        $commission = CommissionAffilie::where('commande_id', $order->id)->first();
        $this->assertNotNull($commission);

        // Validate commission calculation inputs
        $article = $order->articles()->first();
        $this->assertNotNull($article);

        // Log detailed calculation for audit
        $calculationDetails = [
            'product_id' => $product->id,
            'product_title' => $product->titre,
            'prix_achat' => $product->prix_achat,
            'prix_vente' => $product->prix_vente,
            'prix_affilie' => $product->prix_affilie,
            'sale_price' => $article->prix_unitaire,
            'quantity' => $article->quantite,
            'base_amount' => $commission->base_amount,
            'commission_rate' => $commission->rate,
            'commission_amount' => $commission->amount,
            'calculation_rule' => $this->determineCalculationRule($product, $article),
        ];

        Log::info('Commission Calculation Audit Trail', $calculationDetails);

        // Verify calculation is correct based on pricing model
        $this->validateCommissionCalculation($calculationDetails);

        Log::info('Test Case 7 Completed Successfully');
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Create a pre-order for testing
     */
    private function createPreOrder(array $config): Commande
    {
        $affiliate = $config['affiliate'] ?? $this->testAffiliate;
        $product = $config['product'];
        $quantity = $config['quantity'];
        $useRecommendedPrice = $config['use_recommended_price'] ?? true;
        $customPrice = $config['custom_price'] ?? null;

        // Create client and address
        $client = Client::create([
            'nom_complet' => 'Test Client',
            'telephone' => '+212 6 12 34 56 78',
            'email' => 'client@test.com',
        ]);

        $adresse = Adresse::create([
            'client_id' => $client->id,
            'ville' => 'Casablanca',
            'adresse' => 'Test Address, Casablanca',
            'code_postal' => '20000',
        ]);

        // Create order
        $order = Commande::create([
            'boutique_id' => $this->testProducts['boutique']->id,
            'user_id' => $affiliate->id,
            'client_id' => $client->id,
            'adresse_id' => $adresse->id,
            'offre_id' => $this->testProducts['offre']->id,
            'statut' => 'pending',
            'mode_paiement' => 'cod',
            'total_ht' => 0,
            'total_ttc' => 0,
            'devise' => 'MAD',
        ]);

        // Add order line
        $this->addOrderLine($order, [
            'product' => $product,
            'quantity' => $quantity,
            'custom_price' => $useRecommendedPrice ? null : $customPrice,
        ]);

        return $order;
    }

    /**
     * Add an order line to an existing order
     */
    private function addOrderLine(Commande $order, array $config): CommandeArticle
    {
        $product = $config['product'];
        $quantity = $config['quantity'];
        $customPrice = $config['custom_price'] ?? null;

        $unitPrice = $customPrice ?? $product->prix_recommande;
        $totalPrice = $unitPrice * $quantity;

        $article = CommandeArticle::create([
            'commande_id' => $order->id,
            'produit_id' => $product->id,
            'quantite' => $quantity,
            'prix_unitaire' => $unitPrice,
            'prix_total' => $totalPrice,
        ]);

        // Update order totals
        $order->update([
            'total_ht' => $order->articles()->sum('prix_total'),
            'total_ttc' => $order->articles()->sum('prix_total'),
        ]);

        return $article;
    }

    /**
     * Confirm an order (change status from pending to confirmed)
     */
    private function confirmOrder(Commande $order): void
    {
        $order->update(['statut' => 'confirmed']);
    }

    /**
     * Create local shipping for an order
     */
    private function createLocalShipping(Commande $order): ShippingParcel
    {
        return ShippingParcel::create([
            'commande_id' => $order->id,
            'provider' => 'local',
            'tracking_number' => 'LOCAL-' . strtoupper(uniqid()),
            'status' => 'pending',
            'sent_to_carrier' => false,
            'receiver' => $order->client->nom_complet,
            'phone' => $order->client->telephone,
            'address' => $order->adresse->adresse,
            'price' => 30.00,
        ]);
    }

    /**
     * Create carrier shipping for an order
     */
    private function createCarrierShipping(Commande $order): ShippingParcel
    {
        return ShippingParcel::create([
            'commande_id' => $order->id,
            'provider' => 'ozonexpress',
            'tracking_number' => 'OZN-' . strtoupper(uniqid()),
            'status' => 'pending',
            'sent_to_carrier' => true,
            'receiver' => $order->client->nom_complet,
            'phone' => $order->client->telephone,
            'address' => $order->adresse->adresse,
            'price' => 25.00,
        ]);
    }

    /**
     * Update shipping status and trigger events
     */
    private function updateShippingStatus(ShippingParcel $parcel, string $newStatus): void
    {
        $oldStatus = $parcel->commande->statut;

        // Update parcel status
        $parcel->update(['status' => $newStatus]);

        // Update order status
        $parcel->commande->update(['statut' => $newStatus]);

        // Fire OrderDelivered event if status is delivered
        if ($newStatus === 'livree' && $oldStatus !== 'livree') {
            OrderDelivered::dispatch($parcel->commande, 'manual_update', [
                'previous_status' => $oldStatus,
                'parcel_id' => $parcel->id,
            ]);

            // Process the event synchronously for testing
            $this->commissionService->calculateForOrder($parcel->commande);
        }
    }

    /**
     * Simulate carrier webhook status update
     */
    private function simulateCarrierWebhook(ShippingParcel $parcel, string $status): void
    {
        $statusMapping = [
            'picked_up' => 'expediee',
            'out_for_delivery' => 'en_cours_livraison',
            'delivered' => 'livree',
        ];

        $orderStatus = $statusMapping[$status] ?? $status;
        $this->updateShippingStatus($parcel, $orderStatus);
    }

    // ========================================
    // PRICING MODEL VALIDATION METHODS
    // ========================================

    /**
     * Create products with specific pricing for validation tests
     */
    private function createPricingTestProducts(): array
    {
        $boutique = $this->testProducts['boutique'];
        $categorie = Categorie::first();

        // Product for recommended price testing
        $recommendedProduct = Produit::create([
            'titre' => 'Pricing Test - Recommended',
            'description' => 'Product for testing recommended price commission',
            'prix_achat' => 100.00,      // Cost price
            'prix_vente' => 150.00,      // Recommended retail price
            'prix_affilie' => 50.00,     // Fixed commission amount
            'slug' => 'pricing-test-recommended-' . uniqid(),
            'boutique_id' => $boutique->id,
            'categorie_id' => $categorie->id,
            'actif' => true,
            'quantite_min' => 1,
            'stock_total' => 100,
        ]);

        // Product for modified price testing
        $modifiedProduct = Produit::create([
            'titre' => 'Pricing Test - Modified',
            'description' => 'Product for testing modified price commission',
            'prix_achat' => 80.00,       // Cost price
            'prix_vente' => 120.00,      // Recommended retail price
            'prix_affilie' => null,      // No fixed commission (use calculation)
            'slug' => 'pricing-test-modified-' . uniqid(),
            'boutique_id' => $boutique->id,
            'categorie_id' => $categorie->id,
            'actif' => true,
            'quantite_min' => 1,
            'stock_total' => 100,
        ]);

        // Product for edge case testing
        $edgeProduct = Produit::create([
            'titre' => 'Pricing Test - Edge Case',
            'description' => 'Product for testing edge cases',
            'prix_achat' => 90.00,       // Cost price
            'prix_vente' => 110.00,      // Recommended retail price
            'prix_affilie' => 15.00,     // Fixed commission amount
            'slug' => 'pricing-test-edge-' . uniqid(),
            'boutique_id' => $boutique->id,
            'categorie_id' => $categorie->id,
            'actif' => true,
            'quantite_min' => 1,
            'stock_total' => 100,
        ]);

        return [
            'recommended' => $recommendedProduct,
            'modified' => $modifiedProduct,
            'edge' => $edgeProduct,
        ];
    }

    /**
     * Validate recommended price scenario
     */
    private function validateRecommendedPriceScenario(Produit $product): void
    {
        Log::info('Validating Recommended Price Scenario', [
            'product_id' => $product->id,
            'prix_achat' => $product->prix_achat,
            'prix_vente' => $product->prix_vente,
            'prix_affilie' => $product->prix_affilie,
        ]);

        // Create order using recommended price
        $order = $this->createPreOrder([
            'product' => $product,
            'quantity' => 2,
            'use_recommended_price' => true,
        ]);

        $this->confirmOrder($order);
        $shippingParcel = $this->createLocalShipping($order);
        $this->updateShippingStatus($shippingParcel, 'livree');

        // Verify commission calculation
        $commission = CommissionAffilie::where('commande_id', $order->id)->first();
        $this->assertNotNull($commission);

        $article = $order->articles()->first();
        $this->assertEquals($product->prix_vente, $article->prix_unitaire);

        // Expected commission: prix_affilie if set, otherwise (prix_vente - prix_achat) * qty
        $expectedCommission = $product->prix_affilie
            ? $product->prix_affilie * $article->quantite
            : ($product->prix_vente - $product->prix_achat) * $article->quantite;

        $this->assertEquals($expectedCommission, $commission->amount,
            "Recommended price commission should be {$expectedCommission}, got {$commission->amount}");

        Log::info('Recommended Price Scenario Validated', [
            'expected_commission' => $expectedCommission,
            'actual_commission' => $commission->amount,
        ]);
    }

    /**
     * Validate modified price higher scenario
     */
    private function validateModifiedPriceHigherScenario(Produit $product): void
    {
        $higherPrice = $product->prix_vente + 30.00; // 120 + 30 = 150

        Log::info('Validating Modified Price Higher Scenario', [
            'product_id' => $product->id,
            'prix_achat' => $product->prix_achat,
            'prix_vente' => $product->prix_vente,
            'modified_price' => $higherPrice,
        ]);

        // Create order with higher price
        $order = $this->createPreOrder([
            'product' => $product,
            'quantity' => 1,
            'use_recommended_price' => false,
            'custom_price' => $higherPrice,
        ]);

        $this->confirmOrder($order);
        $shippingParcel = $this->createCarrierShipping($order);
        $this->updateShippingStatus($shippingParcel, 'livree');

        // Verify commission calculation
        $commission = CommissionAffilie::where('commande_id', $order->id)->first();
        $this->assertNotNull($commission);

        $article = $order->articles()->first();
        $this->assertEquals($higherPrice, $article->prix_unitaire);

        // Expected commission: (sale_price - prix_achat) * qty
        $expectedCommission = ($higherPrice - $product->prix_achat) * $article->quantite;

        $this->assertEquals($expectedCommission, $commission->amount,
            "Modified higher price commission should be {$expectedCommission}, got {$commission->amount}");

        Log::info('Modified Price Higher Scenario Validated', [
            'sale_price' => $higherPrice,
            'cost_price' => $product->prix_achat,
            'expected_commission' => $expectedCommission,
            'actual_commission' => $commission->amount,
        ]);
    }

    /**
     * Validate modified price lower scenario
     */
    private function validateModifiedPriceLowerScenario(Produit $product): void
    {
        $lowerPrice = $product->prix_vente - 15.00; // 120 - 15 = 105

        Log::info('Validating Modified Price Lower Scenario', [
            'product_id' => $product->id,
            'prix_achat' => $product->prix_achat,
            'prix_vente' => $product->prix_vente,
            'modified_price' => $lowerPrice,
        ]);

        // Create order with lower price
        $order = $this->createPreOrder([
            'product' => $product,
            'quantity' => 1,
            'use_recommended_price' => false,
            'custom_price' => $lowerPrice,
        ]);

        $this->confirmOrder($order);
        $shippingParcel = $this->createCarrierShipping($order);
        $this->updateShippingStatus($shippingParcel, 'livree');

        // Verify commission calculation
        $commission = CommissionAffilie::where('commande_id', $order->id)->first();
        $this->assertNotNull($commission);

        $article = $order->articles()->first();
        $this->assertEquals($lowerPrice, $article->prix_unitaire);

        // Expected commission: (sale_price - prix_achat) * qty
        $expectedCommission = ($lowerPrice - $product->prix_achat) * $article->quantite;

        $this->assertEquals($expectedCommission, $commission->amount,
            "Modified lower price commission should be {$expectedCommission}, got {$commission->amount}");

        Log::info('Modified Price Lower Scenario Validated', [
            'sale_price' => $lowerPrice,
            'cost_price' => $product->prix_achat,
            'expected_commission' => $expectedCommission,
            'actual_commission' => $commission->amount,
        ]);
    }

    /**
     * Validate price at cost scenario (edge case)
     */
    private function validatePriceAtCostScenario(Produit $product): void
    {
        $costPrice = $product->prix_achat; // Sell at cost price

        Log::info('Validating Price at Cost Scenario', [
            'product_id' => $product->id,
            'prix_achat' => $product->prix_achat,
            'sale_price' => $costPrice,
        ]);

        // Create order at cost price
        $order = $this->createPreOrder([
            'product' => $product,
            'quantity' => 1,
            'use_recommended_price' => false,
            'custom_price' => $costPrice,
        ]);

        $this->confirmOrder($order);
        $shippingParcel = $this->createLocalShipping($order);
        $this->updateShippingStatus($shippingParcel, 'livree');

        // Verify commission calculation
        $commission = CommissionAffilie::where('commande_id', $order->id)->first();
        $this->assertNotNull($commission);

        $article = $order->articles()->first();
        $this->assertEquals($costPrice, $article->prix_unitaire);

        // Expected commission: (sale_price - prix_achat) * qty = 0
        $expectedCommission = 0.00;

        $this->assertEquals($expectedCommission, $commission->amount,
            "Price at cost commission should be {$expectedCommission}, got {$commission->amount}");

        Log::info('Price at Cost Scenario Validated', [
            'sale_price' => $costPrice,
            'cost_price' => $product->prix_achat,
            'expected_commission' => $expectedCommission,
            'actual_commission' => $commission->amount,
        ]);
    }

    /**
     * Determine which calculation rule was applied
     */
    private function determineCalculationRule(Produit $product, CommandeArticle $article): string
    {
        if ($article->prix_unitaire == $product->prix_vente) {
            return $product->prix_affilie ? 'FIXED_COMMISSION' : 'RECOMMENDED_PRICE_MARGIN';
        } else {
            return 'MODIFIED_PRICE_MARGIN';
        }
    }

    /**
     * Validate commission calculation based on pricing model
     */
    private function validateCommissionCalculation(array $details): void
    {
        $rule = $details['calculation_rule'];
        $expectedCommission = 0;

        switch ($rule) {
            case 'FIXED_COMMISSION':
                $expectedCommission = $details['prix_affilie'] * $details['quantity'];
                break;

            case 'RECOMMENDED_PRICE_MARGIN':
                $expectedCommission = ($details['prix_vente'] - $details['prix_achat']) * $details['quantity'];
                break;

            case 'MODIFIED_PRICE_MARGIN':
                $expectedCommission = ($details['sale_price'] - $details['prix_achat']) * $details['quantity'];
                break;
        }

        $this->assertEquals($expectedCommission, $details['commission_amount'],
            "Commission calculation for rule {$rule} should be {$expectedCommission}, got {$details['commission_amount']}");

        Log::info('Commission Calculation Validated', [
            'rule' => $rule,
            'expected' => $expectedCommission,
            'actual' => $details['commission_amount'],
        ]);
    }
}
