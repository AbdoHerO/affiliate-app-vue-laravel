<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\CommissionAffilie;
use App\Models\Withdrawal;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Produit;
use App\Models\ProduitVariante;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\Adresse;
use App\Models\Expedition;
use App\Models\ExpeditionEvenement;
use App\Models\ShippingParcel;
use App\Models\Transporteur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AffiliateQADataSeeder extends Seeder
{
    private $affiliateUserId = '0198cd28-0b1f-7170-a26f-61e13ab21d72';
    private $statusDistribution = [
        'pending' => 15,
        'confirmed' => 20,
        'sent' => 15,
        'delivered' => 30,
        'canceled' => 5,
        'returned' => 5,
        'delivery_failed' => 5,
        'paid' => 5,
    ];

    public function run(): void
    {
        $this->command->info('Creating rich QA data for affiliate user...');

        DB::transaction(function () {
            $this->createOrders();
            $this->createWithdrawals();
            $this->createTickets();
        });

        $this->command->info('Affiliate QA data created successfully!');
    }

    private function createOrders(): void
    {
        $this->command->info('Creating orders...');
        
        $user = User::find($this->affiliateUserId);
        if (!$user) {
            $this->command->error('Affiliate user not found!');
            return;
        }

        // Get some products and boutiques for realistic data
        $products = Produit::with('variantes')->take(20)->get();
        $boutiques = Boutique::take(5)->get();
        
        if ($products->isEmpty() || $boutiques->isEmpty()) {
            $this->command->warn('No products or boutiques found. Creating basic ones...');
            $this->createBasicProductsAndBoutiques();
            $products = Produit::with('variantes')->take(20)->get();
            $boutiques = Boutique::take(5)->get();
        }

        $totalOrders = 140;
        $ordersCreated = 0;

        foreach ($this->statusDistribution as $status => $percentage) {
            $orderCount = (int) ($totalOrders * $percentage / 100);
            
            for ($i = 0; $i < $orderCount; $i++) {
                $this->createSingleOrder($user, $products, $boutiques, $status);
                $ordersCreated++;
            }
        }

        $this->command->info("Created {$ordersCreated} orders");
    }

    private function createSingleOrder($user, $products, $boutiques, $status): void
    {
        // Create random date in last 180 days with weekday bias
        $daysAgo = rand(1, 180);
        $date = Carbon::now()->subDays($daysAgo);

        // Bias towards weekdays
        if ($date->isWeekend() && rand(1, 3) === 1) {
            $date = $date->addDays(rand(1, 2));
        }

        // Create client
        $client = Client::create([
            'nom_complet' => fake()->name(),
            'telephone' => fake()->phoneNumber(),
            'email' => fake()->email(),
        ]);

        // Create address
        $adresse = Adresse::create([
            'client_id' => $client->id,
            'adresse' => fake()->address(),
            'ville' => fake()->city(),
            'code_postal' => fake()->postcode(),
            'pays' => 'Maroc',
        ]);

        // Get user's affiliate profile
        $affiliateProfile = $user->profilAffilie;
        if (!$affiliateProfile) {
            $this->command->error('User does not have an affiliate profile!');
            return;
        }

        // Create order
        $totalHT = rand(100, 2000);
        $totalTTC = $totalHT * 1.2; // 20% tax

        $order = Commande::create([
            'boutique_id' => $boutiques->random()->id,
            'user_id' => $user->id,
            'affilie_id' => $affiliateProfile->id,
            'client_id' => $client->id,
            'adresse_id' => $adresse->id,
            'statut' => $status,
            'confirmation_cc' => rand(0, 1) ? 'confirme' : 'a_confirmer',
            'mode_paiement' => 'cod',
            'total_ht' => $totalHT,
            'total_ttc' => $totalTTC,
            'devise' => 'MAD',
            'notes' => rand(0, 3) === 0 ? fake()->sentence() : null,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // Create order articles (1-4 items)
        $itemCount = rand(1, 4);
        for ($i = 0; $i < $itemCount; $i++) {
            $product = $products->random();
            $variant = $product->variantes->isNotEmpty() ? $product->variantes->random() : null;
            $quantity = rand(1, 3);
            $unitPrice = rand(50, 500);

            $totalLigne = $quantity * $unitPrice;

            $article = CommandeArticle::create([
                'commande_id' => $order->id,
                'produit_id' => $product->id,
                'variante_id' => $variant?->id,
                'quantite' => $quantity,
                'prix_unitaire' => $unitPrice,
                'total_ligne' => $totalLigne,
            ]);

            // Create commission for this article
            $this->createCommission($user, $order, $article, $status);
        }

        // Create shipping data for shipped orders
        if (in_array($status, ['sent', 'delivered', 'returned', 'delivery_failed'])) {
            $this->createShippingData($order, $status, $boutiques);
        }
    }

    private function createCommission($user, $order, $article, $orderStatus): void
    {
        $commissionRate = 0.05 + (rand(0, 50) / 1000); // 5-10% commission
        $baseAmount = $article->prix_unitaire * $article->quantite;
        $commissionAmount = $baseAmount * $commissionRate;

        // Determine commission status based on order status
        $commissionStatus = match($orderStatus) {
            'pending', 'confirmed' => 'pending',
            'sent', 'delivered' => rand(0, 1) ? 'eligible' : 'paid',
            'paid' => 'paid',
            'canceled', 'returned' => 'canceled',
            default => 'pending'
        };

        $commission = CommissionAffilie::create([
            'commande_article_id' => $article->id,
            'commande_id' => $order->id,
            'user_id' => $user->id,
            'affilie_id' => $user->profilAffilie->id,
            'type' => 'sale',
            'base_amount' => $baseAmount,
            'rate' => $commissionRate,
            'qty' => $article->quantite,
            'amount' => $commissionAmount,
            'currency' => 'MAD',
            'status' => $commissionStatus,
            'rule_code' => 'STANDARD_COMMISSION',
            'eligible_at' => $commissionStatus === 'eligible' ? $order->created_at->addDays(7) : null,
            'approved_at' => $commissionStatus === 'paid' ? $order->created_at->addDays(14) : null,
            'paid_at' => $commissionStatus === 'paid' ? $order->created_at->addDays(21) : null,
        ]);

        // Occasionally create adjusted commissions
        if (rand(1, 20) === 1) {
            CommissionAffilie::create([
                'commande_article_id' => $article->id,
                'commande_id' => $order->id,
                'user_id' => $user->id,
                'affilie_id' => $user->profilAffilie->id,
                'type' => 'adjustment',
                'base_amount' => $baseAmount,
                'rate' => -0.01, // Negative adjustment
                'qty' => $article->quantite,
                'amount' => -($baseAmount * 0.01),
                'currency' => 'MAD',
                'status' => 'approved',
                'rule_code' => 'QUALITY_ADJUSTMENT',
                'notes' => 'Quality issue adjustment',
                'approved_at' => $order->created_at->addDays(10),
            ]);
        }
    }

    private function createShippingData($order, $status, $boutiques): void
    {
        // Get or create a transporteur
        $transporteur = Transporteur::firstOrCreate([
            'nom' => 'OzonExpress',
            'boutique_id' => $boutiques->first()->id,
        ], [
            'actif' => true,
        ]);

        // Create shipping parcel
        $parcel = ShippingParcel::create([
            'commande_id' => $order->id,
            'tracking_number' => 'TRK' . strtoupper(fake()->bothify('##??##??')),
            'provider' => 'ozonexpress',
            'status' => $status === 'delivered' ? 'delivered' : ($status === 'returned' ? 'returned' : 'in_transit'),
            'city_name' => fake()->city(),
            'receiver' => $order->client->nom_complet ?? fake()->name(),
            'phone' => $order->client->telephone ?? fake()->phoneNumber(),
            'address' => $order->adresse->adresse ?? fake()->address(),
            'price' => $order->total_ttc,
            'created_at' => $order->created_at->addHours(rand(2, 24)),
        ]);

        // Create expedition
        $expedition = Expedition::create([
            'commande_id' => $order->id,
            'transporteur_id' => $transporteur->id,
            'tracking_no' => $parcel->tracking_number,
            'statut' => $this->mapStatusToExpedition($status),
            'poids_kg' => rand(1, 10) / 10, // 0.1 to 1.0 kg
            'frais_transport' => rand(20, 50),
            'created_at' => $order->created_at->addHours(rand(2, 24)),
        ]);

        // Create tracking events
        $this->createTrackingEvents($expedition, $status);
    }

    private function mapStatusToExpedition($orderStatus): string
    {
        return match($orderStatus) {
            'sent' => 'en_cours',
            'delivered' => 'livree',
            'returned' => 'retour',
            'delivery_failed' => 'echec',
            default => 'preparee'
        };
    }

    private function createTrackingEvents($expedition, $finalStatus): void
    {
        $events = [
            ['status' => 'picked_up', 'description' => 'Colis récupéré', 'hours' => 0],
            ['status' => 'in_transit', 'description' => 'En transit vers le centre de tri', 'hours' => 4],
            ['status' => 'out_for_delivery', 'description' => 'En cours de livraison', 'hours' => 24],
        ];

        if ($finalStatus === 'delivered') {
            $events[] = ['status' => 'delivered', 'description' => 'Livré avec succès', 'hours' => 26];
        } elseif ($finalStatus === 'delivery_failed') {
            $events[] = ['status' => 'delivery_failed', 'description' => 'Échec de livraison - client absent', 'hours' => 26];
        } elseif ($finalStatus === 'returned') {
            $events[] = ['status' => 'delivery_failed', 'description' => 'Échec de livraison', 'hours' => 26];
            $events[] = ['status' => 'returned', 'description' => 'Colis retourné à l\'expéditeur', 'hours' => 72];
        }

        foreach ($events as $event) {
            ExpeditionEvenement::create([
                'expedition_id' => $expedition->id,
                'code' => $event['status'],
                'message' => $event['description'],
                'occured_at' => $expedition->created_at->addHours($event['hours']),
            ]);
        }
    }

    private function createWithdrawals(): void
    {
        $this->command->info('Creating withdrawals...');
        
        $user = User::find($this->affiliateUserId);
        $withdrawalCount = 8;

        for ($i = 0; $i < $withdrawalCount; $i++) {
            $amount = rand(500, 5000);
            $daysAgo = rand(10, 150);
            $createdAt = Carbon::now()->subDays($daysAgo);

            $statuses = ['pending', 'approved', 'in_payment', 'paid', 'rejected'];
            $status = $statuses[array_rand($statuses)];

            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'status' => $status,
                'method' => 'bank_transfer',
                'iban_rib' => $user->rib,
                'bank_type' => $user->bank_type,
                'notes' => rand(0, 2) === 0 ? fake()->sentence() : null,
                'admin_reason' => $status === 'rejected' ? 'Documents manquants' : null,
                'payment_ref' => $status === 'paid' ? 'PAY' . strtoupper(fake()->bothify('##??##')) : null,
                'approved_at' => in_array($status, ['approved', 'in_payment', 'paid']) ? $createdAt->addDays(2) : null,
                'paid_at' => $status === 'paid' ? $createdAt->addDays(5) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function createTickets(): void
    {
        $this->command->info('Creating support tickets...');
        
        $user = User::find($this->affiliateUserId);
        $ticketCount = 20;

        $categories = ['general', 'orders', 'payments', 'commissions', 'kyc', 'technical', 'other'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $statuses = ['open', 'pending', 'waiting_user', 'resolved', 'closed'];

        for ($i = 0; $i < $ticketCount; $i++) {
            $daysAgo = rand(1, 120);
            $createdAt = Carbon::now()->subDays($daysAgo);
            $status = $statuses[array_rand($statuses)];

            $ticket = Ticket::create([
                'subject' => fake()->sentence(rand(4, 8)),
                'status' => $status,
                'priority' => $priorities[array_rand($priorities)],
                'category' => $categories[array_rand($categories)],
                'requester_id' => $user->id,
                'last_activity_at' => $createdAt,
                'resolved_at' => $status === 'resolved' ? $createdAt->addDays(rand(1, 5)) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Create messages for the ticket
            $this->createTicketMessages($ticket, $user);
        }
    }

    private function createTicketMessages($ticket, $user): void
    {
        $messageCount = rand(2, 8);
        $currentDate = $ticket->created_at;

        for ($i = 0; $i < $messageCount; $i++) {
            $isUserMessage = $i === 0 || rand(0, 1); // First message always from user

            // For support messages, we'll use a support user or create one
            if (!$isUserMessage) {
                $supportUser = User::where('email', 'support@cod.test')->first();
                if (!$supportUser) {
                    $supportUser = User::create([
                        'nom_complet' => 'Support Team',
                        'email' => 'support@cod.test',
                        'password' => bcrypt('password'),
                        'approval_status' => 'approved',
                    ]);
                    $supportUser->assignRole('admin');
                }
                $senderId = $supportUser->id;
            } else {
                $senderId = $user->id;
            }

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $senderId,
                'type' => 'public',
                'body' => fake()->paragraph(rand(2, 5)),
                'created_at' => $currentDate,
                'updated_at' => $currentDate,
            ]);

            $currentDate = $currentDate->addHours(rand(2, 48));
        }

        // Update ticket's last activity
        $ticket->update(['last_activity_at' => $currentDate]);
    }

    private function createBasicProductsAndBoutiques(): void
    {
        // Create a basic boutique
        Boutique::create([
            'nom' => 'Boutique Test',
            'adresse' => 'Casablanca, Morocco',
            'telephone' => '+212 5 22 00 00 00',
            'proprietaire_id' => $this->affiliateUserId,
        ]);

        // Create basic products
        for ($i = 1; $i <= 10; $i++) {
            $product = Produit::create([
                'titre' => "Produit Test {$i}",
                'description' => fake()->paragraph(),
                'prix_unitaire' => rand(50, 500),
                'statut' => 'actif',
            ]);

            // Create variants for some products
            if (rand(0, 1)) {
                ProduitVariante::create([
                    'produit_id' => $product->id,
                    'nom' => 'Taille M - Couleur Rouge',
                    'prix_supplement' => rand(0, 50),
                ]);
            }
        }
    }
}
