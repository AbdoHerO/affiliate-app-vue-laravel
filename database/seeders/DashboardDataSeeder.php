<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Faker\Factory as Faker;

class DashboardDataSeeder extends Seeder
{
    private $faker;
    private $startDate;
    private $endDate;

    public function __construct()
    {
        $this->faker = Faker::create('fr_FR');
        $this->startDate = Carbon::now()->subYear(); // 1 year ago
        $this->endDate = Carbon::now(); // Now to avoid future dates
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Dashboard Data Seeder...');

        // Check if we should seed large data
        $seedLarge = config('app.env') === 'local' || env('SEED_BIG', false);
        $affiliateCount = $seedLarge ? 100 : 25; // Reduced from 500/50 to 100/25
        $ordersPerAffiliate = $seedLarge ? 5 : 2; // Reduced from 10/3 to 5/2

        $this->command->info("📊 Seeding {$affiliateCount} affiliates with realistic dashboard data...");

        DB::transaction(function () use ($affiliateCount, $ordersPerAffiliate) {
            // 1. Create affiliates with realistic data spread over time
            $this->createAffiliates($affiliateCount);
            
            // 2. Create referral codes and clicks
            $this->createReferralData();
            
            // 3. Create orders with realistic patterns
            $this->createOrders($ordersPerAffiliate);
            
            // 4. Create commissions
            $this->createCommissions();
            
            // 5. Create withdrawal requests
            $this->createWithdrawals();
            
            // 6. Create support tickets
            $this->createTickets();
            
            // 7. Create referral attributions (signups from referrals)
            $this->createReferralAttributions();
        });

        $this->command->info('✅ Dashboard Data Seeder completed successfully!');
    }

    private function createAffiliates(int $count): void
    {
        $this->command->info("👥 Creating {$count} affiliates...");

        $affiliates = [];
        $profiles = [];

        for ($i = 0; $i < $count; $i++) {
            $createdAt = $this->faker->dateTimeBetween($this->startDate, $this->endDate);
            
            $affiliates[] = [
                'id' => $this->faker->uuid,
                'nom_complet' => $this->faker->name,
                'email' => $this->faker->unique()->safeEmail,
                'mot_de_passe_hash' => Hash::make('password'),
                'email_verifie' => $this->faker->boolean(80), // 80% verified
                'statut' => $this->faker->randomElement(['actif', 'actif', 'actif', 'suspendu']), // 75% active
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        // Batch insert users and collect UUIDs
        $userIds = [];
        foreach (array_chunk($affiliates, 100) as $chunk) {
            DB::table('users')->insert($chunk);
            // Collect the UUIDs from the chunk
            foreach ($chunk as $affiliate) {
                $userIds[] = $affiliate['id'];
            }
        }

        // Assign affiliate role to all users
        $roleAssignments = [];
        foreach ($userIds as $userId) {
            $roleAssignments[] = [
                'role_id' => 2, // Assuming affiliate role has ID 2
                'model_type' => 'App\\Models\\User',
                'model_id' => $userId,
            ];

            // Create affiliate profile
            $profiles[] = [
                'id' => $this->faker->uuid,
                'utilisateur_id' => $userId,
                'points' => $this->faker->numberBetween(0, 1000),
                'gamme_id' => null, // Set to null since gammes might not exist yet
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Batch insert role assignments
        foreach (array_chunk($roleAssignments, 100) as $chunk) {
            DB::table('model_has_roles')->insert($chunk);
        }

        // Batch insert profiles
        foreach (array_chunk($profiles, 100) as $chunk) {
            DB::table('profils_affilies')->insert($chunk);
        }

        $this->command->info("✅ Created {$count} affiliates with profiles");
    }

    private function createReferralData(): void
    {
        $this->command->info("🔗 Creating referral codes and clicks...");

        $affiliates = DB::table('profils_affilies')->get();
        $referralCodes = [];
        $clicks = [];
        $counter = 1;

        foreach ($affiliates as $affiliate) {
            // Create referral code for each affiliate
            $code = 'REF' . str_pad($counter, 6, '0', STR_PAD_LEFT);
            // Ensure the affiliate creation date is not in the future
            $affiliateCreatedAt = Carbon::parse($affiliate->created_at);
            if ($affiliateCreatedAt->isFuture()) {
                $affiliateCreatedAt = $this->startDate;
            }

            $referralCodes[] = [
                'id' => $this->faker->uuid,
                'affiliate_id' => $affiliate->id,
                'code' => $code,
                'active' => true,
                'created_at' => $affiliateCreatedAt,
                'updated_at' => $affiliateCreatedAt,
            ];

            // Create random clicks for this referral code (reduced for performance)
            $clickCount = $this->faker->numberBetween(5, 50);
            for ($i = 0; $i < $clickCount; $i++) {
                $clicks[] = [
                    'id' => $this->faker->uuid,
                    'referral_code' => $code,
                    'ip_address' => $this->faker->ipv4,
                    'user_agent' => $this->faker->userAgent,
                    'source' => $this->faker->randomElement(['google', 'facebook', 'instagram', 'direct', 'email']),
                    'created_at' => $this->faker->dateTimeBetween($affiliateCreatedAt, min($this->endDate, Carbon::now())),
                    'updated_at' => $this->faker->dateTimeBetween($affiliateCreatedAt, min($this->endDate, Carbon::now())),
                ];
            }
            $counter++;
        }

        // Batch insert referral codes
        foreach (array_chunk($referralCodes, 100) as $chunk) {
            DB::table('referral_codes')->insert($chunk);
        }

        // Batch insert clicks
        foreach (array_chunk($clicks, 500) as $chunk) {
            DB::table('referral_clicks')->insert($chunk);
        }

        $this->command->info("✅ Created referral codes and " . count($clicks) . " clicks");
    }

    private function createOrders(int $ordersPerAffiliate): void
    {
        $this->command->info("🛒 Creating orders...");

        // Get affiliates using direct DB query since User::role() might not work yet
        $affiliates = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.role_id', 2) // Affiliate role
            ->select('users.*')
            ->get();

        foreach ($affiliates as $affiliate) {
            $orderCount = $this->faker->numberBetween(1, $ordersPerAffiliate);

            for ($i = 0; $i < $orderCount; $i++) {
                $startOrderDate = max($affiliate->created_at, $this->startDate);
                $endOrderDate = min($this->endDate, Carbon::now());
                $orderDate = $this->faker->dateTimeBetween($startOrderDate, $endOrderDate);
                $total = $this->faker->randomFloat(2, 50, 500);

                // Get existing boutiques, clients, and addresses
                $boutiques = DB::table('boutiques')->pluck('id')->toArray();
                $clients = DB::table('clients')->pluck('id')->toArray();
                $adresses = DB::table('adresses')->pluck('id')->toArray();
                $profiles = DB::table('profils_affilies')->where('utilisateur_id', $affiliate->id)->pluck('id')->toArray();

                if (empty($boutiques) || empty($clients) || empty($adresses) || empty($profiles)) {
                    continue; // Skip if required data doesn't exist
                }

                $orderId = $this->faker->uuid;
                DB::table('commandes')->insert([
                    'id' => $orderId,
                    'user_id' => $affiliate->id,
                    'boutique_id' => $this->faker->randomElement($boutiques),
                    'affilie_id' => $this->faker->randomElement($profiles),
                    'client_id' => $this->faker->randomElement($clients),
                    'adresse_id' => $this->faker->randomElement($adresses),
                    'statut' => $this->faker->randomElement(['en_attente', 'confirmee', 'expediee', 'livree', 'annulee']),
                    'confirmation_cc' => $this->faker->randomElement(['non_contacte', 'a_confirmer', 'confirme', 'injoignable']),
                    'mode_paiement' => 'cod',
                    'total_ht' => $total * 0.8,
                    'total_ttc' => $total,
                    'devise' => 'MAD',
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                // Create order articles
                $produits = DB::table('produits')->pluck('id')->toArray();
                if (!empty($produits)) {
                    $articleCount = $this->faker->numberBetween(1, 3);
                    for ($j = 0; $j < $articleCount; $j++) {
                        DB::table('commande_articles')->insert([
                            'id' => $this->faker->uuid,
                            'commande_id' => $orderId,
                            'produit_id' => $this->faker->randomElement($produits),
                            'quantite' => $this->faker->numberBetween(1, 3),
                            'prix_unitaire' => $this->faker->randomFloat(2, 20, 200),
                            'total_ligne' => $this->faker->randomFloat(2, 20, 300),
                            'created_at' => $orderDate,
                            'updated_at' => $orderDate,
                        ]);
                    }
                }
            }
        }

        $this->command->info("✅ Created orders for affiliates");
    }

    private function createCommissions(): void
    {
        $this->command->info("💰 Creating commissions...");

        $orders = DB::table('commandes')->get();
        $commissions = [];

        foreach ($orders as $order) {
            // Create commission for each order (assuming 10-20% commission rate)
            $rate = $this->faker->randomFloat(4, 0.10, 0.20);
            $baseAmount = $order->total_ttc;
            $amount = $baseAmount * $rate;

            $commissions[] = [
                'id' => $this->faker->uuid,
                'user_id' => $order->user_id,
                'commande_id' => $order->id,
                'affilie_id' => null, // Will be set by the system
                'commande_article_id' => null, // Order-level commission
                'type' => 'vente',
                'base_amount' => $baseAmount,
                'rate' => $rate,
                'qty' => 1,
                'amount' => $amount,
                'montant' => $amount, // Legacy field
                'currency' => 'MAD',
                'status' => $this->faker->randomElement(['calculated', 'eligible', 'approved', 'paid']),
                'statut' => $this->faker->randomElement(['en_attente', 'valide', 'paye']), // Legacy field
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        }

        // Batch insert commissions
        foreach (array_chunk($commissions, 100) as $chunk) {
            DB::table('commissions_affilies')->insert($chunk);
        }

        $this->command->info("✅ Created " . count($commissions) . " commissions");
    }

    private function createWithdrawals(): void
    {
        $this->command->info("💸 Creating withdrawal requests...");

        // Get affiliates using direct DB query
        $affiliates = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.role_id', 2) // Affiliate role
            ->select('users.*')
            ->get();

        $withdrawals = [];

        foreach ($affiliates as $affiliate) {
            // 30% chance of having withdrawal requests
            if ($this->faker->boolean(30)) {
                $withdrawalCount = $this->faker->numberBetween(1, 3);

                for ($i = 0; $i < $withdrawalCount; $i++) {
                    $withdrawals[] = [
                        'id' => $this->faker->uuid,
                        'user_id' => $affiliate->id,
                        'amount' => $this->faker->randomFloat(2, 50, 500),
                        'method' => 'bank_transfer', // Only bank_transfer is supported
                        'status' => $this->faker->randomElement(['pending', 'approved', 'paid', 'rejected']),
                        'iban_rib' => $this->faker->iban('MA'),
                        'bank_type' => $this->faker->randomElement(['Attijariwafa Bank', 'BMCE Bank', 'Banque Populaire']),
                        'notes' => $this->faker->optional()->sentence,
                        'created_at' => $this->faker->dateTimeBetween(max($affiliate->created_at, $this->startDate), min($this->endDate, Carbon::now())),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // Batch insert withdrawals
        foreach (array_chunk($withdrawals, 100) as $chunk) {
            DB::table('withdrawals')->insert($chunk);
        }

        $this->command->info("✅ Created " . count($withdrawals) . " withdrawal requests");
    }

    private function createTickets(): void
    {
        $this->command->info("🎫 Creating support tickets...");

        // Get affiliates using direct DB query
        $affiliates = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.role_id', 2) // Affiliate role
            ->select('users.*')
            ->get();

        $tickets = [];

        foreach ($affiliates as $affiliate) {
            // 20% chance of having tickets
            if ($this->faker->boolean(20)) {
                $ticketCount = $this->faker->numberBetween(1, 2);

                for ($i = 0; $i < $ticketCount; $i++) {
                    $tickets[] = [
                        'id' => $this->faker->uuid,
                        'requester_id' => $affiliate->id,
                        'subject' => $this->faker->sentence,
                        'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
                        'status' => $this->faker->randomElement(['open', 'pending', 'resolved', 'closed']),
                        'category' => $this->faker->randomElement(['general', 'orders', 'payments', 'commissions']),
                        'last_activity_at' => $this->faker->dateTimeBetween(max($affiliate->created_at, $this->startDate), min($this->endDate, Carbon::now())),
                        'created_at' => $this->faker->dateTimeBetween(max($affiliate->created_at, $this->startDate), min($this->endDate, Carbon::now())),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // Batch insert tickets
        foreach (array_chunk($tickets, 100) as $chunk) {
            DB::table('tickets')->insert($chunk);
        }

        $this->command->info("✅ Created " . count($tickets) . " support tickets");
    }

    private function createReferralAttributions(): void
    {
        $this->command->info("👥 Creating referral attributions (signups from referrals)...");

        $affiliates = DB::table('profils_affilies')->get();
        $existingUsers = DB::table('users')->pluck('id')->toArray();
        $attributions = [];

        foreach ($affiliates as $affiliate) {
            // Each affiliate gets some referral signups (reduced for performance)
            $signupCount = $this->faker->numberBetween(0, 5);

            for ($i = 0; $i < $signupCount; $i++) {
                $attributions[] = [
                    'id' => $this->faker->uuid,
                    'referrer_affiliate_id' => $affiliate->id,
                    'new_user_id' => $this->faker->randomElement($existingUsers), // Use existing user IDs
                    'verified' => $this->faker->boolean(70), // 70% verified
                    'source' => $this->faker->randomElement(['google', 'facebook', 'instagram', 'direct']),
                    'created_at' => $this->faker->dateTimeBetween(max($affiliate->created_at, $this->startDate), min($this->endDate, Carbon::now())),
                    'updated_at' => now(),
                ];
            }
        }

        // Batch insert attributions
        foreach (array_chunk($attributions, 100) as $chunk) {
            DB::table('referral_attributions')->insert($chunk);
        }

        $this->command->info("✅ Created " . count($attributions) . " referral attributions");
    }
}
