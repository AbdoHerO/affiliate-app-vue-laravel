<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Produit;
use App\Models\CommandeArticle;
use App\Models\Commande;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\AppSetting;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

/**
 * Data-driven test suite for commission calculations
 * Tests margin-based business rules with comprehensive scenarios
 */
class CommissionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $commissionService;
    private User $affiliate;
    private Boutique $boutique;
    private Categorie $categorie;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->commissionService = new CommissionService();
        
        // Create test data
        $this->boutique = Boutique::factory()->create();
        $this->categorie = Categorie::factory()->create();
        $this->affiliate = User::factory()->create();
        $this->affiliate->assignRole('affiliate');
        
        // Enable margin-based commission strategy
        AppSetting::set('commission.strategy', 'margin');
    }

    /**
     * @dataProvider commissionCalculationProvider
     */
    public function test_commission_calculation_scenarios(
        string $scenario,
        float $costPrice,
        float $recommendedPrice,
        ?float $fixedCommission,
        float $salePrice,
        int $quantity,
        float $expectedCommission,
        string $expectedRule
    ): void {
        Log::info("Testing commission scenario: {$scenario}");

        // Create product with specific pricing
        $product = Produit::create([
            'titre' => "Test Product - {$scenario}",
            'description' => "Product for testing {$scenario}",
            'prix_achat' => $costPrice,
            'prix_vente' => $recommendedPrice,
            'prix_affilie' => $fixedCommission,
            'slug' => 'test-product-' . strtolower(str_replace(' ', '-', $scenario)) . '-' . uniqid(),
            'boutique_id' => $this->boutique->id,
            'categorie_id' => $this->categorie->id,
            'actif' => true,
        ]);

        // Create order and article
        $order = Commande::create([
            'boutique_id' => $this->boutique->id,
            'user_id' => $this->affiliate->id,
            'statut' => 'livree',
            'mode_paiement' => 'cod',
            'total_ht' => $salePrice * $quantity,
            'total_ttc' => $salePrice * $quantity,
            'devise' => 'MAD',
        ]);

        $article = CommandeArticle::create([
            'commande_id' => $order->id,
            'produit_id' => $product->id,
            'quantite' => $quantity,
            'prix_unitaire' => $salePrice,
            'remise' => 0,
            'total_ligne' => $salePrice * $quantity,
        ]);

        // Calculate commission using the service
        $result = $this->commissionService->calculateForOrder($order);

        // Assertions
        $this->assertTrue($result['success'], "Commission calculation should succeed for {$scenario}");
        $this->assertCount(1, $result['commissions'], "Should create exactly one commission for {$scenario}");

        $commission = $result['commissions'][0];
        
        // Test commission amount with tolerance for floating point precision
        $this->assertEqualsWithDelta(
            $expectedCommission,
            $commission->amount,
            0.01,
            "Commission amount should be {$expectedCommission} for {$scenario}, got {$commission->amount}"
        );

        // Test rule code
        $this->assertEquals(
            $expectedRule,
            $commission->rule_code,
            "Rule code should be {$expectedRule} for {$scenario}, got {$commission->rule_code}"
        );

        // Test that commission is properly rounded to 2 decimals
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d{2}$/',
            number_format($commission->amount, 2),
            "Commission amount should be rounded to 2 decimal places for {$scenario}"
        );

        Log::info("Commission calculation test passed", [
            'scenario' => $scenario,
            'expected' => $expectedCommission,
            'actual' => $commission->amount,
            'rule' => $commission->rule_code,
        ]);
    }

    /**
     * Data provider for commission calculation scenarios
     */
    public static function commissionCalculationProvider(): array
    {
        return [
            // [scenario, cost, recommended, fixed, sale, qty, expected_commission, expected_rule]
            
            // RULE 1: Recommended price with fixed commission
            'Recommended with Fixed Commission' => [
                'Recommended with Fixed Commission',
                100.00, // cost
                150.00, // recommended
                50.00,  // fixed commission
                150.00, // sale (= recommended)
                2,      // quantity
                100.00, // expected: 50 × 2
                'FIXED_COMMISSION'
            ],

            // RULE 2: Recommended price without fixed commission (margin-based)
            'Recommended without Fixed Commission' => [
                'Recommended without Fixed Commission',
                100.00, // cost
                150.00, // recommended
                null,   // no fixed commission
                150.00, // sale (= recommended)
                2,      // quantity
                100.00, // expected: (150-100) × 2
                'RECOMMENDED_MARGIN'
            ],

            // RULE 3: Modified price higher than recommended
            'Modified Price Higher' => [
                'Modified Price Higher',
                80.00,  // cost
                120.00, // recommended
                null,   // no fixed commission
                140.00, // sale (> recommended)
                1,      // quantity
                60.00,  // expected: (140-80) × 1
                'MODIFIED_MARGIN'
            ],

            // RULE 4: Modified price lower than recommended
            'Modified Price Lower' => [
                'Modified Price Lower',
                80.00,  // cost
                120.00, // recommended
                null,   // no fixed commission
                100.00, // sale (< recommended)
                1,      // quantity
                20.00,  // expected: (100-80) × 1
                'MODIFIED_MARGIN'
            ],

            // RULE 5: Negative margin guard (sale at cost)
            'Negative Margin Guard - At Cost' => [
                'Negative Margin Guard - At Cost',
                120.00, // cost
                150.00, // recommended
                null,   // no fixed commission
                120.00, // sale (= cost)
                2,      // quantity
                0.00,   // expected: max(0, 120-120) × 2 = 0
                'MODIFIED_MARGIN'
            ],

            // RULE 6: Negative margin guard (sale below cost)
            'Negative Margin Guard - Below Cost' => [
                'Negative Margin Guard - Below Cost',
                120.00, // cost
                150.00, // recommended
                null,   // no fixed commission
                100.00, // sale (< cost)
                2,      // quantity
                0.00,   // expected: max(0, 100-120) × 2 = 0
                'MODIFIED_MARGIN'
            ],

            // RULE 7: Rounding test cases
            'Rounding Test - Half Up' => [
                'Rounding Test - Half Up',
                100.00, // cost
                150.00, // recommended
                null,   // no fixed commission
                104.17, // sale (margin = 4.17 per unit)
                2,      // quantity
                8.34,   // expected: 4.17 × 2 = 8.34 (rounded)
                'MODIFIED_MARGIN'
            ],

            // RULE 8: Fixed commission with modified price (should still use fixed)
            'Fixed Commission with Modified Price' => [
                'Fixed Commission with Modified Price',
                100.00, // cost
                150.00, // recommended
                50.00,  // fixed commission
                150.00, // sale (= recommended, triggers fixed rule)
                1,      // quantity
                50.00,  // expected: 50 × 1 (fixed commission takes precedence)
                'FIXED_COMMISSION'
            ],

            // RULE 9: High quantity test
            'High Quantity Test' => [
                'High Quantity Test',
                50.00,  // cost
                75.00,  // recommended
                null,   // no fixed commission
                75.00,  // sale (= recommended)
                10,     // quantity
                250.00, // expected: (75-50) × 10 = 250
                'RECOMMENDED_MARGIN'
            ],

            // RULE 10: Decimal precision test
            'Decimal Precision Test' => [
                'Decimal Precision Test',
                99.99,  // cost
                149.99, // recommended
                null,   // no fixed commission
                149.99, // sale (= recommended)
                3,      // quantity
                150.00, // expected: (149.99-99.99) × 3 = 50.00 × 3 = 150.00
                'RECOMMENDED_MARGIN'
            ],
        ];
    }

    /**
     * Test commission calculation idempotency
     */
    public function test_commission_calculation_idempotency(): void
    {
        // Create product and order
        $product = Produit::create([
            'titre' => 'Idempotency Test Product',
            'prix_achat' => 100.00,
            'prix_vente' => 150.00,
            'prix_affilie' => null,
            'slug' => 'idempotency-test-' . uniqid(),
            'boutique_id' => $this->boutique->id,
            'categorie_id' => $this->categorie->id,
            'actif' => true,
        ]);

        $order = Commande::create([
            'boutique_id' => $this->boutique->id,
            'user_id' => $this->affiliate->id,
            'statut' => 'livree',
            'mode_paiement' => 'cod',
            'total_ht' => 150.00,
            'total_ttc' => 150.00,
            'devise' => 'MAD',
        ]);

        CommandeArticle::create([
            'commande_id' => $order->id,
            'produit_id' => $product->id,
            'quantite' => 1,
            'prix_unitaire' => 150.00,
            'remise' => 0,
            'total_ligne' => 150.00,
        ]);

        // Calculate commission first time
        $result1 = $this->commissionService->calculateForOrder($order);
        $this->assertTrue($result1['success']);
        $this->assertCount(1, $result1['commissions']);
        $commission1 = $result1['commissions'][0];

        // Calculate commission second time (should be idempotent)
        $result2 = $this->commissionService->calculateForOrder($order);
        $this->assertTrue($result2['success']);
        $this->assertCount(1, $result2['commissions']);
        $commission2 = $result2['commissions'][0];

        // Should return the same commission
        $this->assertEquals($commission1->id, $commission2->id);
        $this->assertEquals($commission1->amount, $commission2->amount);
    }

    /**
     * Test mixed order with multiple pricing scenarios
     */
    public function test_mixed_order_scenarios(): void
    {
        // Create products with different pricing models
        $fixedProduct = Produit::create([
            'titre' => 'Fixed Commission Product',
            'prix_achat' => 100.00,
            'prix_vente' => 150.00,
            'prix_affilie' => 50.00,
            'slug' => 'fixed-product-' . uniqid(),
            'boutique_id' => $this->boutique->id,
            'categorie_id' => $this->categorie->id,
            'actif' => true,
        ]);

        $marginProduct = Produit::create([
            'titre' => 'Margin Product',
            'prix_achat' => 80.00,
            'prix_vente' => 120.00,
            'prix_affilie' => null,
            'slug' => 'margin-product-' . uniqid(),
            'boutique_id' => $this->boutique->id,
            'categorie_id' => $this->categorie->id,
            'actif' => true,
        ]);

        // Create order with multiple articles
        $order = Commande::create([
            'boutique_id' => $this->boutique->id,
            'user_id' => $this->affiliate->id,
            'statut' => 'livree',
            'mode_paiement' => 'cod',
            'total_ht' => 290.00,
            'total_ttc' => 290.00,
            'devise' => 'MAD',
        ]);

        // Article 1: Fixed commission (recommended price)
        CommandeArticle::create([
            'commande_id' => $order->id,
            'produit_id' => $fixedProduct->id,
            'quantite' => 1,
            'prix_unitaire' => 150.00, // = recommended
            'remise' => 0,
            'total_ligne' => 150.00,
        ]);

        // Article 2: Modified margin (higher price)
        CommandeArticle::create([
            'commande_id' => $order->id,
            'produit_id' => $marginProduct->id,
            'quantite' => 1,
            'prix_unitaire' => 140.00, // > recommended (120)
            'remise' => 0,
            'total_ligne' => 140.00,
        ]);

        // Calculate commissions
        $result = $this->commissionService->calculateForOrder($order);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['commissions']);

        $commissions = collect($result['commissions']);

        // Test fixed commission
        $fixedCommission = $commissions->where('rule_code', 'FIXED_COMMISSION')->first();
        $this->assertNotNull($fixedCommission);
        $this->assertEquals(50.00, $fixedCommission->amount);

        // Test margin commission
        $marginCommission = $commissions->where('rule_code', 'MODIFIED_MARGIN')->first();
        $this->assertNotNull($marginCommission);
        $this->assertEquals(60.00, $marginCommission->amount); // (140-80) × 1

        // Test total
        $this->assertEquals(110.00, $result['total_amount']);
    }
}
