<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Models\Produit;
use App\Models\Categorie;
use App\Models\Boutique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class SalesReportsApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $affiliate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Create affiliate user
        $this->affiliate = User::factory()->create();
        $this->affiliate->assignRole('affiliate');

        // Create test data
        $this->createTestData();
    }

    /**
     * Test sales summary endpoint requires admin role
     */
    public function test_sales_summary_requires_admin_role()
    {
        // Test unauthenticated access
        $response = $this->getJson('/api/admin/reports/sales/summary');
        $response->assertStatus(401);

        // Test affiliate access
        Sanctum::actingAs($this->affiliate);
        $response = $this->getJson('/api/admin/reports/sales/summary');
        $response->assertStatus(403);

        // Test admin access
        Sanctum::actingAs($this->admin);
        $response = $this->getJson('/api/admin/reports/sales/summary');
        $response->assertStatus(200);
    }

    /**
     * Test sales summary returns correct structure
     */
    public function test_sales_summary_structure()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/sales/summary');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_sales' => [
                             'value',
                             'delta',
                             'currency',
                         ],
                         'orders_count' => [
                             'value',
                             'delta',
                         ],
                         'avg_order_value' => [
                             'value',
                             'currency',
                         ],
                         'delivered_rate' => [
                             'value',
                             'unit',
                         ],
                         'return_rate' => [
                             'value',
                             'unit',
                         ],
                         'commissions_accrued' => [
                             'value',
                             'currency',
                         ],
                     ],
                 ]);

        $this->assertTrue($response->json('success'));
    }

    /**
     * Test sales summary with date filters
     */
    public function test_sales_summary_with_date_filters()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/sales/summary?' . http_build_query([
            'date_start' => now()->subDays(7)->toDateString(),
            'date_end' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $data = $response->json('data');

        // Verify numeric values
        $this->assertIsNumeric($data['total_sales']['value']);
        $this->assertIsNumeric($data['orders_count']['value']);
        $this->assertIsNumeric($data['avg_order_value']['value']);
        $this->assertIsNumeric($data['delivered_rate']['value']);
        $this->assertIsNumeric($data['return_rate']['value']);
        $this->assertIsNumeric($data['commissions_accrued']['value']);
    }

    /**
     * Test sales series endpoint
     */
    public function test_sales_series_endpoint()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/sales/series?' . http_build_query([
            'period' => 'day',
            'date_start' => now()->subDays(7)->toDateString(),
            'date_end' => now()->toDateString(),
        ]));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'sales_over_time' => [
                             'labels',
                             'datasets',
                         ],
                         'orders_over_time' => [
                             'labels',
                             'datasets',
                         ],
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertIsArray($data['sales_over_time']['labels']);
        $this->assertIsArray($data['sales_over_time']['datasets']);
        $this->assertIsArray($data['orders_over_time']['labels']);
        $this->assertIsArray($data['orders_over_time']['datasets']);
    }

    /**
     * Test status breakdown endpoint
     */
    public function test_status_breakdown_endpoint()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/sales/status-breakdown');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'labels',
                         'datasets',
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertIsArray($data['labels']);
        $this->assertIsArray($data['datasets']);
    }

    /**
     * Test top products endpoint
     */
    public function test_top_products_endpoint()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/sales/top-products?' . http_build_query([
            'limit' => 5,
        ]));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'labels',
                         'datasets',
                         'table_data',
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertIsArray($data['labels']);
        $this->assertIsArray($data['datasets']);
        $this->assertIsArray($data['table_data']);
    }

    /**
     * Test orders table endpoint with pagination
     */
    public function test_orders_table_endpoint()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/sales/orders?' . http_build_query([
            'page' => 1,
            'per_page' => 10,
        ]));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'data',
                         'pagination' => [
                             'current_page',
                             'per_page',
                             'total',
                             'last_page',
                         ],
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertIsArray($data['data']);
        $this->assertIsNumeric($data['pagination']['current_page']);
        $this->assertIsNumeric($data['pagination']['total']);
    }

    /**
     * Test top affiliates endpoint
     */
    public function test_top_affiliates_endpoint()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/sales/top-affiliates?' . http_build_query([
            'limit' => 10,
        ]));

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data',
                 ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    /**
     * Test caching behavior
     */
    public function test_caching_behavior()
    {
        Sanctum::actingAs($this->admin);

        // First request
        $start = microtime(true);
        $response1 = $this->getJson('/api/admin/reports/sales/summary');
        $time1 = microtime(true) - $start;

        // Second request (should be faster due to caching)
        $start = microtime(true);
        $response2 = $this->getJson('/api/admin/reports/sales/summary');
        $time2 = microtime(true) - $start;

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Verify responses are identical
        $this->assertEquals($response1->json(), $response2->json());

        // Second request should be faster (cached)
        $this->assertLessThan($time1, $time2);
    }

    /**
     * Test error handling for invalid date ranges
     */
    public function test_invalid_date_range_handling()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/sales/summary?' . http_build_query([
            'date_start' => 'invalid-date',
            'date_end' => 'invalid-date',
        ]));

        // Should still return 200 with default date range
        $response->assertStatus(200);
    }

    /**
     * Create test data for reports
     */
    private function createTestData()
    {
        // Create categories and boutiques
        $category = Categorie::factory()->create();
        $boutique = Boutique::factory()->create();

        // Create products
        $products = Produit::factory()->count(5)->create([
            'categorie_id' => $category->id,
            'boutique_id' => $boutique->id,
        ]);

        // Create orders with different statuses
        $statuses = ['livre', 'echec', 'retour', 'annule', 'confirme', 'en_attente'];
        
        foreach ($statuses as $status) {
            Commande::factory()->count(3)->create([
                'user_id' => $this->affiliate->id,
                'statut' => $status,
                'total_ttc' => $this->faker->numberBetween(100, 1000),
                'created_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        // Create commissions
        $deliveredOrders = Commande::where('statut', 'livre')->get();
        foreach ($deliveredOrders as $order) {
            CommissionAffilie::factory()->create([
                'user_id' => $this->affiliate->id,
                'commande_id' => $order->id,
                'amount' => $order->total_ttc * 0.1, // 10% commission
                'status' => 'approved',
            ]);
        }
    }
}
