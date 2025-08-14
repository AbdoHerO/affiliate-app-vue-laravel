<?php

namespace Database\Seeders;

/*
# ORDERS SYSTEM AUDIT SUMMARY

## ✅ COMPLETED COMPONENTS

### Backend (Controllers & Models)
- ✅ **PreordersController**: Full CRUD, filtering, pagination, status transitions
- ✅ **ShippingOrdersController**: List/show shipping orders with parcels
- ✅ **OzonExpressController**: Add parcel, tracking, delivery notes, idempotency
- ✅ **Commande Model**: Proper relationships to users.id (not affilie), articles, shipping
- ✅ **CommandeArticle Model**: Order items with product/variant relationships
- ✅ **ShippingParcel Model**: Tracking, status, delivery info
- ✅ **ShippingCity Model**: City data for shipping providers

### Frontend (Pages & Stores)
- ✅ **Pre-Orders Pages**: /admin/orders/pre/* with filters, pagination, actions
- ✅ **Shipping Orders Pages**: /admin/orders/shipping/* with tracking, delivery notes
- ✅ **Preorders Store**: Full state management, API integration
- ✅ **Shipping Store**: Parcel management, tracking, delivery note actions

### Key Features Working
- ✅ **Filters & Pagination**: Search, status, date range, affiliate filtering
- ✅ **Status Transitions**: Order status management (en_attente → confirmee → expediee)
- ✅ **Send to OzonExpress**: Idempotent parcel creation from pre-orders
- ✅ **Tracking Integration**: Real-time tracking data from OzonExpress API
- ✅ **Delivery Notes**: PDF generation (A4, tickets, 100x100 formats)
- ✅ **User Relationships**: All orders link to users.id (approved affiliates)

## 🔄 IN PROGRESS
- 🔄 **Order Status Updates**: Manual status transitions in admin
- 🔄 **Bulk Actions**: Multi-select operations on orders
- 🔄 **Advanced Reporting**: Order analytics and commission calculations

## 📋 NEXT STEPS
- 📋 **Order Editing**: Allow modification of order details
- 📋 **Return Management**: Handle returned/refused orders
- 📋 **Commission Tracking**: Link orders to affiliate commissions
- 📋 **Inventory Integration**: Stock management with order fulfillment
- 📋 **Notification System**: Email/SMS notifications for status changes
- 📋 **Mobile Optimization**: Responsive design improvements

## 🔧 SAFE SWITCHES ADDED
- 🔧 **OZONEXPRESS_ENABLED**: .env toggle for API calls (defaults to true)
- 🔧 **Mock Responses**: When disabled, returns test data for local development

## 🎯 TESTING READY
- 🎯 **E2E Seeder**: Creates realistic test data across all order statuses
- 🎯 **Sample Data**: 25 orders, 15 clients, 5 affiliates, shipping parcels
- 🎯 **Admin Testing**: Both pre-orders and shipping interfaces populated
*/

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Client;
use App\Models\Adresse;
use App\Models\Boutique;
use App\Models\Produit;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\ShippingParcel;
use App\Models\ProfilAffilie;
use Spatie\Permission\Models\Role;

class OrdersE2ETestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Orders E2E Test Seeder...');

        // Ensure affiliate role exists
        Role::firstOrCreate(['name' => 'affiliate']);

        // 1. Ensure N approved affiliate users exist
        $this->command->info('📝 Creating approved affiliate users...');
        $affiliateUsers = $this->createAffiliateUsers(8);
        $this->command->info("✅ Created {$affiliateUsers->count()} affiliate users");

        // 2. Ensure products + variants exist
        $this->command->info('📦 Ensuring products and variants exist...');
        $products = $this->ensureProductsExist();
        $this->command->info("✅ Found/Created {$products->count()} products with variants");

        // 3. Ensure boutique exists
        $this->command->info('🏪 Ensuring boutique exists...');
        $boutique = $this->ensureBoutiqueExists($affiliateUsers->first());
        $this->command->info("✅ Using boutique: {$boutique->nom}");

        // 4. Create M clients with addresses
        $this->command->info('👥 Creating clients with addresses...');
        $clients = $this->createClients(30);
        $this->command->info("✅ Created {$clients->count()} clients with addresses");

        // 5. Create K commandes across statuses
        $this->command->info('📋 Creating orders across different statuses...');
        $orders = $this->createOrders($affiliateUsers, $clients, $boutique, $products, 60);
        $this->command->info("✅ Created {$orders->count()} orders");

        // 6. Create shipping parcels for subset
        $this->command->info('📦 Creating shipping parcels for confirmed orders...');
        $parcels = $this->createShippingParcels($orders);
        $this->command->info("✅ Created {$parcels->count()} shipping parcels");

        // Output summary
        $this->outputSummary($orders);
    }

    /**
     * Create approved affiliate users
     */
    private function createAffiliateUsers(int $count): \Illuminate\Support\Collection
    {
        $users = collect();
        
        for ($i = 1; $i <= $count; $i++) {
            $user = User::firstOrCreate(
                ['email' => "affiliate{$i}@test.com"],
                [
                    'nom_complet' => "Affilié Test {$i}",
                    'mot_de_passe_hash' => bcrypt('password'),
                    'telephone' => "+212 6 12 34 56 " . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'adresse' => "Adresse Affilié {$i}, Casablanca",
                    'statut' => 'actif',
                    'email_verifie' => true,
                    'kyc_statut' => 'valide',
                    'approval_status' => 'approved',
                    'rib' => '1234567890123456789' . $i,
                    'bank_type' => ['Attijariwafa Bank', 'Banque Populaire', 'BMCE Bank'][$i % 3],
                ]
            );

            // Assign affiliate role
            $user->assignRole('affiliate');

            // Create affiliate profile
            ProfilAffilie::firstOrCreate(
                ['utilisateur_id' => $user->id],
                [
                    'points' => rand(0, 100),
                    'statut' => 'actif',
                    'rib' => $user->rib,
                ]
            );

            $users->push($user);
        }

        return $users;
    }

    /**
     * Ensure products and variants exist (reuse existing)
     */
    private function ensureProductsExist(): \Illuminate\Support\Collection
    {
        $products = Produit::with('variantes')->limit(10)->get();
        
        if ($products->isEmpty()) {
            $this->command->warn('⚠️  No products found. Please run ProductsSeeder first.');
            return collect();
        }

        return $products;
    }

    /**
     * Ensure boutique exists
     */
    private function ensureBoutiqueExists(User $owner): Boutique
    {
        return Boutique::firstOrCreate(
            ['slug' => 'test-boutique'],
            [
                'nom' => 'Boutique Test E2E',
                'proprietaire_id' => $owner->id,
                'email_pro' => 'boutique@test.com',
                'adresse' => 'Adresse Boutique Test, Casablanca',
                'statut' => 'actif',
                'commission_par_defaut' => 0.15,
            ]
        );
    }

    /**
     * Create clients with addresses
     */
    private function createClients(int $count): \Illuminate\Support\Collection
    {
        $clients = collect();
        $cities = ['Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 'Oujda'];

        for ($i = 1; $i <= $count; $i++) {
            $client = Client::firstOrCreate(
                ['email' => "client{$i}@test.com"],
                [
                    'nom_complet' => "Client Test {$i}",
                    'telephone' => "+212 6 98 76 54 " . str_pad($i, 2, '0', STR_PAD_LEFT),
                ]
            );

            // Create address for client if it doesn't exist
            if (!$client->adresses()->exists()) {
                Adresse::create([
                    'client_id' => $client->id,
                    'adresse' => "Adresse Client {$i}, Quartier Test",
                    'ville' => $cities[$i % count($cities)],
                    'code_postal' => '20000',
                    'pays' => 'Maroc',
                    'is_default' => true,
                ]);
            }

            $clients->push($client);
        }

        return $clients;
    }

    /**
     * Create orders across different statuses
     */
    private function createOrders($affiliateUsers, $clients, $boutique, $products, int $totalCount = 60): \Illuminate\Support\Collection
    {
        $orders = collect();

        // Distribute orders across statuses proportionally
        $statusDistribution = [
            'en_attente' => 0.30,   // 30% pending orders
            'confirmee' => 0.25,    // 25% confirmed orders
            'expediee' => 0.20,     // 20% shipped orders
            'livree' => 0.15,       // 15% delivered orders
            'annulee' => 0.06,      // 6% cancelled orders
            'retournee' => 0.04,    // 4% returned orders
        ];

        $statuses = [];
        foreach ($statusDistribution as $status => $percentage) {
            $statuses[$status] = max(1, round($totalCount * $percentage));
        }

        $confirmationStatuses = ['non_contacte', 'a_confirmer', 'confirme', 'injoignable'];
        $paymentModes = ['cod', 'virement', 'carte'];

        foreach ($statuses as $status => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $affiliate = $affiliateUsers->random();
                $affiliateProfile = ProfilAffilie::where('utilisateur_id', $affiliate->id)->first();
                $client = $clients->random();
                $clientAddress = $client->adresses()->first();

                // Calculate totals
                $sousTotal = rand(150, 800);
                $total = $sousTotal + rand(20, 50); // Add shipping

                $order = Commande::create([
                    'boutique_id' => $boutique->id,
                    'user_id' => $affiliate->id,
                    'affilie_id' => $affiliateProfile->id, // Use affiliate profile ID
                    'client_id' => $client->id,
                    'adresse_id' => $clientAddress->id,
                    'statut' => $status,
                    'confirmation_cc' => $confirmationStatuses[array_rand($confirmationStatuses)],
                    'mode_paiement' => $paymentModes[array_rand($paymentModes)],
                    'total_ht' => $sousTotal,
                    'total_ttc' => $total,
                    'devise' => 'MAD',
                    'notes' => "Commande test {$status} #{$i}",
                    'created_at' => now()->subDays(rand(1, 30)),
                ]);

                // Create 1-3 order items
                $this->createOrderItems($order, $products, rand(1, 3));
                $orders->push($order);
            }
        }

        return $orders;
    }

    /**
     * Create order items for a command
     */
    private function createOrderItems(Commande $order, $products, int $itemCount): void
    {
        $selectedProducts = $products->random(min($itemCount, $products->count()));
        
        foreach ($selectedProducts as $product) {
            $variant = $product->variantes->isNotEmpty() ? $product->variantes->random() : null;
            $quantity = rand(1, 3);
            $unitPrice = rand(50, 200);
            $lineTotal = $quantity * $unitPrice;

            CommandeArticle::create([
                'commande_id' => $order->id,
                'produit_id' => $product->id,
                'variante_id' => $variant?->id,
                'quantite' => $quantity,
                'prix_unitaire' => $unitPrice,
                'remise' => 0,
                'total_ligne' => $lineTotal,
            ]);
        }
    }

    /**
     * Create shipping parcels for confirmed orders
     */
    private function createShippingParcels($orders): \Illuminate\Support\Collection
    {
        $parcels = collect();
        $shippableOrders = $orders->whereIn('statut', ['confirmee', 'expediee', 'livree']);
        $statuses = ['pending', 'picked_up', 'in_transit', 'delivered', 'returned'];
        $cities = ['Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger'];

        foreach ($shippableOrders as $order) {
            $parcel = ShippingParcel::create([
                'commande_id' => $order->id,
                'provider' => 'ozonexpress',
                'tracking_number' => 'OZ' . strtoupper(Str::random(8)) . rand(1000, 9999),
                'status' => $statuses[array_rand($statuses)],
                'city_name' => $cities[array_rand($cities)],
                'receiver' => $order->client->nom_complet,
                'phone' => $order->client->telephone,
                'address' => $order->adresse->adresse,
                'price' => rand(25, 45),
                'note' => 'Colis test généré automatiquement',
                'last_synced_at' => now()->subHours(rand(1, 24)),
                'meta' => [
                    'test_data' => true,
                    'created_by_seeder' => true,
                ],
            ]);

            $parcels->push($parcel);
        }

        return $parcels;
    }

    /**
     * Output summary of created data
     */
    private function outputSummary($orders): void
    {
        $this->command->info('');
        $this->command->info('📊 ORDERS E2E TEST DATA SUMMARY');
        $this->command->info('=====================================');
        
        $statusCounts = $orders->groupBy('statut')->map->count();
        foreach ($statusCounts as $status => $count) {
            $this->command->info("• {$status}: {$count} orders");
        }

        $this->command->info('');
        $this->command->info('🔍 SAMPLE ORDER IDs FOR TESTING:');
        $sampleOrders = $orders->take(5);
        foreach ($sampleOrders as $order) {
            $shortId = substr($order->id, 0, 8);
            $this->command->info("• {$order->statut}: {$shortId} (Client: {$order->client->nom_complet})");
        }

        $this->command->info('');
        $this->command->info('🎯 ADMIN TESTING URLS:');
        $this->command->info('• Pre-Orders: /admin/orders/pre');
        $this->command->info('• Shipping Orders: /admin/orders/shipping');
        $this->command->info('');
        $this->command->info('✅ Orders E2E Test Seeder completed successfully!');
    }
}
