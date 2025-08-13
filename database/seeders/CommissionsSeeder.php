<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CommissionAffilie;
use App\Models\Commande;

class CommissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some orders and their articles
        $orders = Commande::with('articles')->take(5)->get();

        foreach ($orders as $order) {
            if ($order->user_id && $order->articles->count() > 0) {
                foreach ($order->articles as $article) {
                    // Get the affiliate profile ID for legacy compatibility
                    $affiliateProfile = \App\Models\ProfilAffilie::where('utilisateur_id', $order->user_id)->first();

                    // Create a commission for this article
                    CommissionAffilie::firstOrCreate([
                        'commande_article_id' => $article->id,
                        'user_id' => $order->user_id,
                    ], [
                        'affilie_id' => $affiliateProfile?->id, // Legacy field
                        'type' => 'vente',
                        'montant' => $article->prix_unitaire * 0.1, // 10% commission
                        'statut' => ['en_attente', 'validee', 'payee'][array_rand(['en_attente', 'validee', 'payee'])],
                        'motif' => 'Commission sur vente',
                    ]);
                }
            }
        }

        $this->command->info('Created commissions for orders');
    }
}
