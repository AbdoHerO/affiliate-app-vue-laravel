<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Commande;
use App\Models\CommandeArticle;
use App\Models\Client;
use App\Models\Adresse;
use App\Models\ProfilAffilie;
use App\Models\Boutique;
use App\Models\Produit;

class OrdersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing data
        $affiliates = ProfilAffilie::with('utilisateur')->take(3)->get();
        $boutiques = Boutique::take(2)->get();
        $produits = Produit::take(5)->get();

        if ($affiliates->isEmpty() || $boutiques->isEmpty() || $produits->isEmpty()) {
            $this->command->warn('Please run other seeders first (users, boutiques, products)');
            return;
        }

        // Create sample clients
        $clients = [];
        for ($i = 1; $i <= 10; $i++) {
            $client = Client::firstOrCreate([
                'email' => 'client' . $i . '@test.com',
            ], [
                'nom_complet' => 'Client' . $i . ' Test',
                'telephone' => '0600000' . str_pad($i, 3, '0', STR_PAD_LEFT),
            ]);

            // Create address for client
            $adresse = Adresse::firstOrCreate([
                'client_id' => $client->id,
            ], [
                'ville' => ['Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger'][array_rand(['Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger'])],
                'adresse' => 'Adresse test ' . $i . ', Quartier Test, Ville',
                'code_postal' => '20000',
                'pays' => 'MA',
                'is_default' => true,
            ]);

            $clients[] = ['client' => $client, 'adresse' => $adresse];
        }

        // Create sample orders
        foreach ($clients as $index => $clientData) {
            $affiliate = $affiliates->random();
            $boutique = $boutiques->random();

            $commande = Commande::create([
                'boutique_id' => $boutique->id,
                'affilie_id' => $affiliate->id,
                'client_id' => $clientData['client']->id,
                'adresse_id' => $clientData['adresse']->id,
                'statut' => ['en_attente', 'confirmee'][array_rand(['en_attente', 'confirmee'])],
                'confirmation_cc' => ['non_contacte', 'a_confirmer', 'confirme'][array_rand(['non_contacte', 'a_confirmer', 'confirme'])],
                'mode_paiement' => 'cod',
                'total_ht' => 0,
                'total_ttc' => 0,
                'devise' => 'MAD',
                'notes' => $index % 3 === 0 ? 'Commande test avec note spéciale' : null,
            ]);

            // Add 1-3 articles per order
            $totalHT = 0;
            $articleCount = rand(1, 3);

            for ($j = 0; $j < $articleCount; $j++) {
                $produit = $produits->random();
                $quantite = rand(1, 3);
                $prixUnitaire = rand(100, 500);
                $totalLigne = $quantite * $prixUnitaire;
                $totalHT += $totalLigne;

                CommandeArticle::create([
                    'commande_id' => $commande->id,
                    'produit_id' => $produit->id,
                    'quantite' => $quantite,
                    'prix_unitaire' => $prixUnitaire,
                    'remise' => 0,
                    'total_ligne' => $totalLigne,
                ]);
            }

            // Update order totals
            $commande->update([
                'total_ht' => $totalHT,
                'total_ttc' => $totalHT, // No tax for now
            ]);
        }

        $this->command->info('Created ' . count($clients) . ' sample orders with clients and addresses');
    }
}
