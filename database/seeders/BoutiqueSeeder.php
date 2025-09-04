<?php

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\User;
use Illuminate\Database\Seeder;

class BoutiqueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the affiliate user created by AffiliateSeeder
        $affiliateUser = User::where('email', 'affiliate@cod.test')->first();

        if (!$affiliateUser) {
            $this->command->error('❌ Affiliate user not found! Make sure AffiliateSeeder runs first.');
            return;
        }

        // Create single production boutique: Tujjar Store
        Boutique::firstOrCreate([
            'slug' => 'tujjar-store',
        ], [
            'nom' => 'Tujjar Store',
            'adresse' => 'Casablanca, Morocco',
            'email_pro' => 'contact@tujjar.shop',
            'statut' => 'actif',
            'commission_par_defaut' => 10.0,
            'proprietaire_id' => $affiliateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('✅ Tujjar Store boutique created successfully!');
        $this->command->info('🏪 Owner: ' . $affiliateUser->email);
    }
}
