<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\Produit;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\Adresse;
use App\Models\Offre;
use App\Models\Categorie;
use App\Models\RegleCommission;
use App\Models\CommissionAffilie;
use App\Models\ShippingParcel;
use App\Models\ProfilAffilie;
use App\Models\GammeAffilie;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class E2ETestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Creating E2E Test Data...');

        // Create roles and permissions
        $this->createRolesAndPermissions();

        // Create the target affiliate
        $affiliate = $this->createTargetAffiliate();

        // Create affiliate profile
        $affiliateProfile = $this->createAffiliateProfile($affiliate);

        // Create supporting data
        $boutique = $this->createBoutique();
        $categorie = $this->createCategorie();
        $products = $this->createProducts($boutique, $categorie);
        $offre = $this->createOffre($boutique, $products[0]); // Use first product for offer
        $client = $this->createClient();
        $adresse = $this->createAdresse($client);

        // Create commission rules
        $this->createCommissionRules($offre);

        // Create test orders with different scenarios
        $this->createTestOrders($affiliate, $affiliateProfile, $boutique, $offre, $products, $client, $adresse);

        $this->command->info('✅ E2E Test Data Created Successfully!');
        $this->command->info("🎯 Target Affiliate ID: {$affiliate->id}");
        $this->command->info("📧 Target Affiliate Email: {$affiliate->email}");
    }

    private function createRolesAndPermissions(): void
    {
        // Create roles
        Role::firstOrCreate(['name' => 'admin']);
        $affiliateRole = Role::firstOrCreate(['name' => 'affiliate']);

        // Create permissions
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

        // Assign permissions to affiliate role
        $affiliateRole->givePermissionTo($permissions);
    }

    private function createTargetAffiliate(): User
    {
        $affiliate = User::firstOrCreate(
            ['id' => '0198cd28-0b1f-7170-a26f-61e13ab21d72'],
            [
                'nom_complet' => 'Test Affiliate E2E',
                'email' => 'test.affiliate.e2e@example.com',
                'mot_de_passe_hash' => Hash::make('password123'),
                'telephone' => '+212 6 12 34 56 78',
                'adresse' => 'Test Address, Casablanca',
                'statut' => 'actif',
                'email_verifie' => true,
                'kyc_statut' => 'valide',
                'approval_status' => 'approved',
                'rib' => '1234567890123456789',
                'bank_type' => 'Attijariwafa Bank',
            ]
        );

        $affiliate->assignRole('affiliate');
        return $affiliate;
    }

    private function createAffiliateProfile(User $affiliate): ProfilAffilie
    {
        return ProfilAffilie::firstOrCreate(
            ['utilisateur_id' => $affiliate->id],
            [
                'gamme_id' => null, // No specific gamme for testing
                'points' => 0,
                'statut' => 'actif',
                'rib' => $affiliate->rib,
                'notes_interne' => 'E2E Test Profile',
            ]
        );
    }

    private function createBoutique(): Boutique
    {
        // First create a proprietaire (owner) user
        $proprietaire = User::firstOrCreate(
            ['email' => 'proprietaire.e2e@test.com'],
            [
                'nom_complet' => 'E2E Boutique Owner',
                'mot_de_passe_hash' => Hash::make('password123'),
                'telephone' => '+212 5 22 00 00 01',
                'adresse' => 'Owner Address, Casablanca',
                'statut' => 'actif',
                'email_verifie' => true,
                'kyc_statut' => 'valide',
                'approval_status' => 'approved',
            ]
        );

        return Boutique::firstOrCreate(
            ['nom' => 'E2E Test Boutique'],
            [
                'slug' => 'e2e-test-boutique',
                'proprietaire_id' => $proprietaire->id,
                'email_pro' => 'boutique.e2e@test.com',
                'adresse' => 'Test Boutique Address, Casablanca',
                'statut' => 'actif',
                'commission_par_defaut' => 0.150, // 15%
            ]
        );
    }

    private function createCategorie(): Categorie
    {
        return Categorie::firstOrCreate(
            ['nom' => 'E2E Test Category'],
            [
                'slug' => 'e2e-test-category',
                'image_url' => null,
                'actif' => true,
                'ordre' => 1,
            ]
        );
    }

    private function createOffre(Boutique $boutique, Produit $product): Offre
    {
        return Offre::firstOrCreate(
            ['titre_public' => 'E2E Test Offer'],
            [
                'boutique_id' => $boutique->id,
                'produit_id' => $product->id,
                'prix_vente' => 150.00,
                'actif' => true,
            ]
        );
    }

    private function createProducts(Boutique $boutique, Categorie $categorie): array
    {
        // Product 1: For recommended price testing
        $product1 = Produit::firstOrCreate(
            ['titre' => 'E2E Test Product 1 - Recommended Price'],
            [
                'description' => 'Product for testing recommended price commission calculation',
                'prix_achat' => 100.00,
                'prix_vente' => 150.00,
                'prix_affilie' => null, // Will use percentage commission
                'slug' => 'e2e-test-product-1-recommended-price',
                'boutique_id' => $boutique->id,
                'categorie_id' => $categorie->id,
                'actif' => true,
                'quantite_min' => 1,
                'stock_total' => 100,
            ]
        );

        // Product 2: For modified price testing
        $product2 = Produit::firstOrCreate(
            ['titre' => 'E2E Test Product 2 - Modified Price'],
            [
                'description' => 'Product for testing modified price commission calculation',
                'prix_achat' => 80.00,
                'prix_vente' => 120.00,
                'prix_affilie' => null, // Will use percentage commission
                'slug' => 'e2e-test-product-2-modified-price',
                'boutique_id' => $boutique->id,
                'categorie_id' => $categorie->id,
                'actif' => true,
                'quantite_min' => 1,
                'stock_total' => 100,
            ]
        );

        return [$product1, $product2];
    }

    private function createClient(): Client
    {
        return Client::firstOrCreate(
            ['telephone' => '+212 6 98 76 54 32'],
            [
                'nom_complet' => 'E2E Test Client',
                'email' => 'client.e2e@test.com',
            ]
        );
    }

    private function createAdresse(Client $client): Adresse
    {
        return Adresse::firstOrCreate(
            ['client_id' => $client->id],
            [
                'adresse' => 'E2E Test Address, Casablanca',
                'ville' => 'Casablanca',
                'code_postal' => '20000',
                'pays' => 'MA',
                'is_default' => true,
            ]
        );
    }

    private function createCommissionRules(Offre $offre): void
    {
        RegleCommission::firstOrCreate(
            ['offre_id' => $offre->id],
            [
                'type' => 'percentage',
                'valeur' => 0.15, // 15% commission
                'actif' => true,
            ]
        );
    }

    private function createTestOrders(User $affiliate, ProfilAffilie $affiliateProfile, Boutique $boutique, Offre $offre, array $products, Client $client, Adresse $adresse): void
    {
        // Order 1: Recommended price, delivered (should have commission)
        $order1 = $this->createOrder($affiliate, $affiliateProfile, $boutique, $offre, $client, $adresse, 'livree');
        $article1 = $this->addOrderArticle($order1, $products[0], 2, $products[0]->prix_vente); // 150 * 2 = 300
        $this->createCommission($affiliate, $affiliateProfile, $order1, $article1, 300.00, 0.15, 45.00);
        $this->createShippingParcel($order1, 'local', 'livree');

        // Order 2: Modified prices, delivered (should have commissions)
        $order2 = $this->createOrder($affiliate, $affiliateProfile, $boutique, $offre, $client, $adresse, 'livree');
        $article2a = $this->addOrderArticle($order2, $products[1], 1, 140.00); // Higher than recommended
        $article2b = $this->addOrderArticle($order2, $products[1], 1, 100.00); // Lower than recommended
        $this->createCommission($affiliate, $affiliateProfile, $order2, $article2a, 140.00, 0.15, 21.00);
        $this->createCommission($affiliate, $affiliateProfile, $order2, $article2b, 100.00, 0.15, 15.00);
        $this->createShippingParcel($order2, 'ozonexpress', 'livree');

        // Order 3: Pending order (no commission yet)
        $order3 = $this->createOrder($affiliate, $affiliateProfile, $boutique, $offre, $client, $adresse, 'confirmed');
        $this->addOrderArticle($order3, $products[0], 1, $products[0]->prix_vente);
        $this->createShippingParcel($order3, 'local', 'pending');
    }

    private function createOrder(User $affiliate, ProfilAffilie $affiliateProfile, Boutique $boutique, Offre $offre, Client $client, Adresse $adresse, string $statut): Commande
    {
        return Commande::create([
            'boutique_id' => $boutique->id,
            'affilie_id' => $affiliateProfile->id,
            'user_id' => $affiliate->id,
            'client_id' => $client->id,
            'adresse_id' => $adresse->id,
            'offre_id' => $offre->id,
            'statut' => $statut,
            'mode_paiement' => 'cod',
            'total_ht' => 0, // Will be updated after adding articles
            'total_ttc' => 0, // Will be updated after adding articles
            'devise' => 'MAD',
        ]);
    }

    private function addOrderArticle(Commande $order, Produit $product, int $quantity, float $unitPrice): CommandeArticle
    {
        $totalPrice = $unitPrice * $quantity;

        $article = CommandeArticle::create([
            'commande_id' => $order->id,
            'produit_id' => $product->id,
            'quantite' => $quantity,
            'prix_unitaire' => $unitPrice,
            'remise' => 0,
            'total_ligne' => $totalPrice,
        ]);

        // Update order totals
        $order->update([
            'total_ht' => $order->articles()->sum('total_ligne'),
            'total_ttc' => $order->articles()->sum('total_ligne'),
        ]);

        return $article;
    }

    private function createCommission(User $affiliate, ProfilAffilie $affiliateProfile, Commande $order, CommandeArticle $article, float $baseAmount, float $rate, float $amount): CommissionAffilie
    {
        return CommissionAffilie::create([
            'commande_article_id' => $article->id,
            'affilie_id' => $affiliateProfile->id,
            'user_id' => $affiliate->id,
            'commande_id' => $order->id,
            'type' => 'vente',
            'base_amount' => $baseAmount,
            'rate' => $rate,
            'amount' => $amount,
            'status' => 'calculated',
            'montant' => $amount, // Legacy field
            'statut' => 'valide', // Legacy field
        ]);
    }

    private function createShippingParcel(Commande $order, string $provider, string $status): ShippingParcel
    {
        return ShippingParcel::create([
            'commande_id' => $order->id,
            'provider' => $provider,
            'tracking_number' => strtoupper($provider) . '-' . strtoupper(uniqid()),
            'status' => $status,
            'sent_to_carrier' => $provider !== 'local',
            'receiver' => $order->client->nom_complet,
            'phone' => $order->client->telephone,
            'address' => $order->adresse->adresse,
            'price' => $provider === 'local' ? 30.00 : 25.00,
        ]);
    }
}
