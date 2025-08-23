<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Produit;
use App\Models\Boutique;
use App\Models\RegleCommission;
use App\Models\Offre;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    // Temporarily disable RefreshDatabase to avoid migration conflicts
    // use RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Don't fake events for E2E tests - we want real event handling
        // Event::fake([]);
        // Queue::fake();

        // Create basic roles and permissions
        $this->createRolesAndPermissions();
    }

    /**
     * Create basic roles and permissions for testing (only if they don't exist)
     */
    protected function createRolesAndPermissions(): void
    {
        // Create roles only if they don't exist
        if (!Role::where('name', 'admin')->exists()) {
            Role::create(['name' => 'admin']);
        }

        $affiliateRole = Role::firstOrCreate(['name' => 'affiliate']);

        // Create permissions only if they don't exist
        $permissions = [
            'create orders',
            'view own orders',
            'view own commissions',
            'request payout',
            'view withdrawals',
            'download withdrawal pdf',
            'download ticket attachment',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to affiliate role (this is safe to call multiple times)
        $affiliateRole->givePermissionTo($permissions);
    }

    /**
     * Create an authenticated affiliate user for testing
     */
    protected function createTestAffiliate(?string $id = null): User
    {
        $user = User::create([
            'id' => $id ?? '0198cd28-0b1f-7170-a26f-61e13ab21d72',
            'nom_complet' => 'Test Affiliate',
            'email' => 'test.affiliate@example.com',
            'mot_de_passe_hash' => bcrypt('password'),
            'telephone' => '+212 6 12 34 56 78',
            'adresse' => 'Test Address, Casablanca',
            'statut' => 'actif',
            'email_verifie' => true,
            'kyc_statut' => 'valide',
            'approval_status' => 'approved',
            'rib' => '1234567890123456789',
            'bank_type' => 'Attijariwafa Bank',
        ]);

        $user->assignRole('affiliate');

        return $user;
    }

    /**
     * Create test products with commission rules
     */
    protected function createTestProducts(): array
    {
        $boutique = Boutique::create([
            'nom' => 'Test Boutique',
            'adresse' => 'Test Address',
            'telephone' => '+212 5 22 00 00 00',
            'email' => 'boutique@test.com',
            'actif' => true,
        ]);

        $offre = Offre::create([
            'nom' => 'Test Offer',
            'description' => 'Test offer for commission rules',
            'actif' => true,
        ]);

        // Product 1: Recommended price scenario
        $product1 = Produit::create([
            'titre' => 'Test Product 1',
            'description' => 'Product for recommended price testing',
            'prix_unitaire' => 100.00,
            'prix_recommande' => 150.00,
            'boutique_id' => $boutique->id,
            'actif' => true,
            'stock' => 100,
        ]);

        // Product 2: Modified price scenario
        $product2 = Produit::create([
            'titre' => 'Test Product 2',
            'description' => 'Product for modified price testing',
            'prix_unitaire' => 80.00,
            'prix_recommande' => 120.00,
            'boutique_id' => $boutique->id,
            'actif' => true,
            'stock' => 100,
        ]);

        // Create commission rules
        RegleCommission::create([
            'offre_id' => $offre->id,
            'type' => 'percentage',
            'valeur' => 0.15, // 15% commission
            'actif' => true,
        ]);

        return [
            'boutique' => $boutique,
            'offre' => $offre,
            'product1' => $product1,
            'product2' => $product2,
        ];
    }

    /**
     * Create an API token for the given user
     */
    protected function createApiToken(User $user): string
    {
        return $user->createToken('test-token')->plainTextToken;
    }

    /**
     * Make an authenticated API request
     */
    protected function apiAs(User $user, string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        $token = $this->createApiToken($user);

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->json($method, $uri, $data);
    }
}
