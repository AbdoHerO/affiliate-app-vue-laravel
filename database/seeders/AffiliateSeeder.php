<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ProfilAffilie;
use App\Models\GammeAffilie;

class AffiliateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create affiliate tier if not exists
        $gamme = GammeAffilie::firstOrCreate([
            'code' => 'BASIC',
        ], [
            'libelle' => 'Basic',
            'actif' => true,
        ]);

        // 1. Create admin user
        $adminUser = User::firstOrCreate([
            'email' => 'admin@cod.test',
        ], [
            'nom_complet' => 'Admin COD',
            'email_verifie' => true,
            'mot_de_passe_hash' => bcrypt('password'),
            'telephone' => '0600000000',
            'statut' => 'actif',
            'kyc_statut' => 'valide',
        ]);

        $adminUser->assignRole('admin');
        $this->command->info('✅ Created admin user: ' . $adminUser->email);

        // 2. Create single affiliate user for production
        $affiliateUser = User::firstOrCreate([
            'email' => 'affiliate@cod.test',
        ], [
            'nom_complet' => 'Affiliate COD',
            'email_verifie' => true,
            'mot_de_passe_hash' => bcrypt('password'),
            'telephone' => '0600000001',
            'statut' => 'actif',
            'kyc_statut' => 'valide',
        ]);

        $affiliateUser->assignRole('affiliate');

        // Create affiliate profile
        ProfilAffilie::firstOrCreate([
            'utilisateur_id' => $affiliateUser->id,
        ], [
            'gamme_id' => $gamme->id,
            'points' => 0, // Start with 0 points for clean production testing
            'statut' => 'actif',
            'rib' => null, // No RIB for clean testing
        ]);

        $this->command->info('✅ Created affiliate user: ' . $affiliateUser->email);
        $this->command->info('🎯 Production-ready users created successfully!');
    }
}
