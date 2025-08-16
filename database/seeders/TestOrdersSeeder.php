<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\Produit;
use App\Models\Adresse;
use App\Services\OzonExpressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestOrdersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating test orders for OzonExpress shipping...');

        // Test customers data
        $testCustomers = [
            [
                'nom_complet' => 'Ahmed Benali',
                'email' => 'ahmed.benali@test.ma',
                'telephone' => '0661234567',
                'ville' => 'Casablanca',
                'adresse' => 'Rue Hassan II, Quartier Maarif, Casablanca',
            ],
            [
                'nom_complet' => 'Fatima Zahra',
                'email' => 'fatima.zahra@test.ma',
                'telephone' => '0672345678',
                'ville' => 'Rabat',
                'adresse' => 'Avenue Mohammed V, Agdal, Rabat',
            ],
            [
                'nom_complet' => 'Youssef Alami',
                'email' => 'youssef.alami@test.ma',
                'telephone' => '0683456789',
                'ville' => 'Marrakech',
                'adresse' => 'Boulevard Mohamed VI, Gueliz, Marrakech',
            ],
            [
                'nom_complet' => 'Aicha Bennani',
                'email' => 'aicha.bennani@test.ma',
                'telephone' => '0694567890',
                'ville' => 'Fès',
                'adresse' => 'Rue Talaa Kebira, Médina, Fès',
            ],
            [
                'nom_complet' => 'Omar Tazi',
                'email' => 'omar.tazi@test.ma',
                'telephone' => '0605678901',
                'ville' => 'Tanger',
                'adresse' => 'Avenue Pasteur, Centre Ville, Tanger',
            ],
        ];

        // Get some products for the orders
        $products = Produit::where('actif', true)->take(10)->get();

        if ($products->isEmpty()) {
            $this->command->error('No active products found. Please run ProductSeeder first.');
            return;
        }

        // Get a boutique for the orders
        $boutique = \App\Models\Boutique::first();
        if (!$boutique) {
            $this->command->error('No boutique found. Please create a boutique first.');
            return;
        }

        // Get an affiliate user for the orders
        $affiliate = \App\Models\User::role('affiliate')->first();
        if (!$affiliate) {
            $this->command->error('No affiliate user found. Please create an affiliate user first.');
            return;
        }

        $ozonService = app(OzonExpressService::class);
        $createdOrders = [];

        foreach ($testCustomers as $customerData) {
            try {
                DB::beginTransaction();

                // Create or get client
                $client = Client::firstOrCreate(
                    ['email' => $customerData['email']],
                    [
                        'nom_complet' => $customerData['nom_complet'],
                        'telephone' => $customerData['telephone'],
                    ]
                );

                // Create address
                $adresse = Adresse::create([
                    'client_id' => $client->id,
                    'adresse' => $customerData['adresse'],
                    'ville' => $customerData['ville'],
                    'code_postal' => '20000',
                    'pays' => 'Maroc',
                    'is_default' => true,
                ]);

                // Create order
                $totalHT = 0;
                $selectedProducts = $products->random(rand(1, 3));
                
                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 2);
                    $price = $product->prix_vente ?? 50;
                    $totalHT += $price * $quantity;
                }

                $tva = $totalHT * 0.20; // 20% VAT
                $totalTTC = $totalHT + $tva;

                $commande = Commande::create([
                    'boutique_id' => $boutique->id,
                    'user_id' => $affiliate->id,
                    'client_id' => $client->id,
                    'adresse_id' => $adresse->id,
                    'statut' => 'confirmee', // Confirmed status
                    'total_ht' => $totalHT,
                    'total_ttc' => $totalTTC,
                    'mode_paiement' => 'cod', // Cash on delivery
                    'notes' => 'Commande de test pour OzonExpress - ' . now()->format('Y-m-d H:i'),
                ]);

                // Add order items
                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 2);
                    $price = $product->prix_vente ?? 50;

                    CommandeArticle::create([
                        'commande_id' => $commande->id,
                        'produit_id' => $product->id,
                        'variante_id' => null,
                        'quantite' => $quantity,
                        'prix_unitaire' => $price,
                        'prix_total' => $price * $quantity,
                    ]);
                }

                $this->command->info("Created order {$commande->id} for {$customerData['nom_complet']}");

                // Send to OzonExpress
                $this->command->info("Sending order {$commande->id} to OzonExpress...");
                
                $result = $ozonService->addParcel($commande);
                
                if ($result['success']) {
                    $this->command->info("✅ Order {$commande->id} sent to OzonExpress successfully!");
                    $this->command->info("   Tracking Number: {$result['data']->tracking_number}");
                    $createdOrders[] = [
                        'order' => $commande,
                        'tracking' => $result['data']->tracking_number,
                        'customer' => $customerData['nom_complet'],
                    ];
                } else {
                    $this->command->error("❌ Failed to send order {$commande->id} to OzonExpress: {$result['message']}");
                }

                DB::commit();

                // Small delay between orders
                sleep(1);

            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("Error creating order for {$customerData['nom_complet']}: " . $e->getMessage());
                Log::error('TestOrdersSeeder Error', [
                    'customer' => $customerData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Summary
        $this->command->info("\n" . str_repeat('=', 60));
        $this->command->info('TEST ORDERS SEEDER SUMMARY');
        $this->command->info(str_repeat('=', 60));
        $this->command->info("Total orders created: " . count($createdOrders));
        
        if (!empty($createdOrders)) {
            $this->command->info("\nCreated Orders:");
            foreach ($createdOrders as $orderInfo) {
                $this->command->info("- {$orderInfo['order']->id} | {$orderInfo['customer']} | {$orderInfo['tracking']}");
            }
            
            $this->command->info("\n📋 Next Steps:");
            $this->command->info("1. Go to 'Order Management > Expéditions' to see the orders");
            $this->command->info("2. Use the refresh tracking buttons to update statuses");
            $this->command->info("3. Check the debug page to track individual parcels");
        }
        
        $this->command->info(str_repeat('=', 60));
    }
}
