<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\Produit;
use App\Models\Client;
use App\Models\Boutique;
use App\Models\Adresse;
use App\Models\ProfilAffilie;
use App\Services\CommissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommissionTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Creating commission test data...');

        DB::transaction(function () {
            // Get or create test affiliate users
            $affiliates = $this->createTestAffiliates();

            // Get or create test boutique first
            $boutique = $this->createTestBoutique();

            // Get or create test products
            $products = $this->createTestProducts($boutique);
            
            // Create test clients
            $clients = $this->createTestClients();
            
            // Create test orders with different statuses
            $orders = $this->createTestOrders($affiliates, $products, $boutique, $clients);
            
            // Calculate commissions for delivered orders
            $this->calculateCommissions($orders);
            
            // Create some historical commissions with different statuses
            $this->createHistoricalCommissions($orders);
        });

        $this->command->info('✅ Commission test data created successfully!');
        $this->command->info('📊 You can now test the commission management system.');
    }

    /**
     * Create test affiliate users
     */
    private function createTestAffiliates(): array
    {
        $this->command->info('👥 Creating test affiliates...');
        
        $affiliates = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $affiliate = User::firstOrCreate(
                ['email' => "affiliate{$i}@test.com"],
                [
                    'nom_complet' => "Affilié Test {$i}",
                    'telephone' => "06123456{$i}0",
                    'mot_de_passe_hash' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'statut' => 'actif',
                ]
            );
            
            // Assign affiliate role
            if (!$affiliate->hasRole('affiliate')) {
                $affiliate->assignRole('affiliate');
            }

            // Create affiliate profile
            $profilAffilie = ProfilAffilie::firstOrCreate(
                ['utilisateur_id' => $affiliate->id],
                [
                    'utilisateur_id' => $affiliate->id,
                    'statut' => 'actif',
                    'points' => 0,
                ]
            );

            $affiliates[] = [
                'user' => $affiliate,
                'profil' => $profilAffilie,
            ];
        }
        
        return $affiliates;
    }

    /**
     * Create test products with commission settings
     */
    private function createTestProducts($boutique): array
    {
        $this->command->info('📦 Creating test products...');
        
        $products = [];
        
        $productData = [
            [
                'titre' => 'Smartphone Test Pro',
                'prix_vente' => 2500.00,
                'prix_affilie' => 150.00, // Fixed commission
            ],
            [
                'titre' => 'Laptop Gaming Test',
                'prix_vente' => 8500.00,
                'prix_affilie' => null, // Will use percentage
            ],
            [
                'titre' => 'Casque Audio Test',
                'prix_vente' => 450.00,
                'prix_affilie' => 25.00, // Fixed commission
            ],
            [
                'titre' => 'Montre Connectée Test',
                'prix_vente' => 1200.00,
                'prix_affilie' => null, // Will use percentage
            ],
            [
                'titre' => 'Tablette Test Ultra',
                'prix_vente' => 3200.00,
                'prix_affilie' => 200.00, // Fixed commission
            ],
        ];

        foreach ($productData as $data) {
            $slug = Str::slug($data['titre']);
            $product = Produit::firstOrCreate(
                ['titre' => $data['titre']],
                [
                    'boutique_id' => $boutique->id,
                    'slug' => $slug,
                    'description' => "Description pour {$data['titre']}",
                    'prix_vente' => $data['prix_vente'],
                    'prix_affilie' => $data['prix_affilie'],
                    'actif' => true,
                    'quantite_min' => 1,
                ]
            );
            
            $products[] = $product;
        }
        
        return $products;
    }

    /**
     * Create test boutique
     */
    private function createTestBoutique()
    {
        // Get first admin user as owner
        $adminUser = User::role('admin')->first();
        if (!$adminUser) {
            // Create a basic admin user if none exists
            $adminUser = User::create([
                'nom_complet' => 'Admin Test',
                'email' => 'admin@test.com',
                'mot_de_passe_hash' => bcrypt('password'),
                'telephone' => '0522000000',
                'statut' => 'actif',
                'email_verified_at' => now(),
            ]);
            $adminUser->assignRole('admin');
        }

        return Boutique::firstOrCreate(
            ['nom' => 'Boutique Test Commissions'],
            [
                'slug' => 'boutique-test-commissions',
                'proprietaire_id' => $adminUser->id,
                'email_pro' => 'boutique@test.com',
                'adresse' => 'Casablanca, Maroc',
                'statut' => 'actif',
                'commission_par_defaut' => 10.000,
            ]
        );
    }

    /**
     * Create test clients
     */
    private function createTestClients(): array
    {
        $this->command->info('👤 Creating test clients...');
        
        $clients = [];
        
        for ($i = 1; $i <= 10; $i++) {
            $client = Client::firstOrCreate(
                ['telephone' => "0612345{$i}00"],
                [
                    'nom_complet' => "Client Test {$i}",
                    'email' => "client{$i}@test.com",
                ]
            );
            
            // Create delivery address
            Adresse::firstOrCreate(
                ['client_id' => $client->id],
                [
                    'adresse' => "Adresse {$i}, Quartier Test",
                    'ville' => 'Casablanca',
                    'pays' => 'Maroc',
                    'code_postal' => '20000',
                ]
            );
            
            $clients[] = $client;
        }
        
        return $clients;
    }

    /**
     * Create test orders with different statuses
     */
    private function createTestOrders($affiliates, $products, $boutique, $clients): array
    {
        $this->command->info('📋 Creating test orders...');
        
        $orders = [];
        $statuses = ['en_attente', 'confirmee', 'expediee', 'livree', 'retournee', 'annulee'];
        
        for ($i = 1; $i <= 20; $i++) {
            $affiliateData = $affiliates[array_rand($affiliates)];
            $affiliate = $affiliateData['user'];
            $profilAffilie = $affiliateData['profil'];
            $client = $clients[array_rand($clients)];
            $status = $statuses[array_rand($statuses)];

            // Create order
            $clientAddress = $client->adresses->first();
            $order = Commande::create([
                'id' => Str::uuid(),
                'user_id' => $affiliate->id, // Link to affiliate user
                'affilie_id' => $profilAffilie->id, // Link to affiliate profile
                'client_id' => $client->id,
                'boutique_id' => $boutique->id,
                'adresse_id' => $clientAddress->id,
                'statut' => $status,
                'total_ht' => 0,
                'total_ttc' => 0,
                'devise' => 'MAD',
                'notes' => "Commande test {$i} - Statut: {$status}",
                'created_at' => now()->subDays(rand(1, 30)),
            ]);
            
            // Add 1-3 products to each order
            $numProducts = rand(1, 3);
            $orderTotal = 0;
            
            for ($j = 0; $j < $numProducts; $j++) {
                $product = $products[array_rand($products)];
                $quantity = rand(1, 3);
                $unitPrice = $product->prix_vente;
                $lineTotal = $unitPrice * $quantity;
                $orderTotal += $lineTotal;
                
                CommandeArticle::create([
                    'id' => Str::uuid(),
                    'commande_id' => $order->id,
                    'produit_id' => $product->id,
                    'quantite' => $quantity,
                    'prix_unitaire' => $unitPrice,
                    'total_ligne' => $lineTotal,
                ]);
            }
            
            // Update order totals
            $order->update([
                'total_ht' => $orderTotal,
                'total_ttc' => $orderTotal * 1.2, // Add 20% tax
            ]);
            
            $orders[] = $order;
        }
        
        return $orders;
    }

    /**
     * Calculate commissions for delivered orders
     */
    private function calculateCommissions($orders): void
    {
        $this->command->info('💰 Calculating commissions...');
        
        $commissionService = app(CommissionService::class);
        
        foreach ($orders as $order) {
            if (in_array($order->statut, ['livree', 'expediee'])) {
                try {
                    $result = $commissionService->calculateForOrder($order);
                    if ($result['success']) {
                        $this->command->info("✅ Commission calculated for order {$order->id}: {$result['total_amount']} MAD");
                    }
                } catch (\Exception $e) {
                    $this->command->error("❌ Failed to calculate commission for order {$order->id}: {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Create historical commissions with different statuses
     */
    private function createHistoricalCommissions($orders): void
    {
        $this->command->info('📈 Creating historical commission data...');
        
        // Get some calculated commissions and update their statuses
        $commissions = \App\Models\CommissionAffilie::where('status', 'calculated')->get();
        
        foreach ($commissions->take(10) as $index => $commission) {
            switch ($index % 5) {
                case 0:
                    // Make eligible (past cooldown)
                    $commission->update([
                        'status' => 'eligible',
                        'eligible_at' => now()->subDays(2),
                    ]);
                    break;
                    
                case 1:
                    // Make approved
                    $commission->update([
                        'status' => 'approved',
                        'eligible_at' => now()->subDays(10),
                        'approved_at' => now()->subDays(3),
                        'notes' => 'Approved by admin - test data',
                    ]);
                    break;
                    
                case 2:
                    // Make paid
                    $commission->update([
                        'status' => 'paid',
                        'eligible_at' => now()->subDays(15),
                        'approved_at' => now()->subDays(8),
                        'paid_at' => now()->subDays(5),
                        'notes' => 'Paid via bank transfer - test data',
                    ]);
                    break;
                    
                case 3:
                    // Make rejected
                    $commission->update([
                        'status' => 'rejected',
                        'notes' => 'Rejected - order returned by customer',
                    ]);
                    break;
                    
                case 4:
                    // Make adjusted
                    $originalAmount = $commission->amount;
                    $newAmount = $originalAmount * 0.8; // 20% reduction
                    $meta = [
                        'adjustments' => [
                            [
                                'original_amount' => $originalAmount,
                                'new_amount' => $newAmount,
                                'difference' => $newAmount - $originalAmount,
                                'reason' => 'Promotional discount applied',
                                'adjusted_at' => now()->subDays(1)->toISOString(),
                                'adjusted_by' => 1, // Admin user ID
                            ]
                        ]
                    ];
                    
                    $commission->update([
                        'status' => 'adjusted',
                        'amount' => $newAmount,
                        'meta' => $meta,
                        'notes' => 'Adjusted - promotional discount applied',
                    ]);
                    break;
            }
        }
    }
}
