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

        // Create multiple test affiliate users
        $affiliateData = [
            [
                'email' => 'affiliate1@test.com',
                'nom_complet' => 'Ahmed Benali',
                'telephone' => '0600000001',
                'statut' => 'actif',
                'kyc_statut' => 'valide',
                'profile_statut' => 'actif',
            ],
            [
                'email' => 'affiliate2@test.com',
                'nom_complet' => 'Fatima Alaoui',
                'telephone' => '0600000002',
                'statut' => 'actif',
                'kyc_statut' => 'en_attente',
                'profile_statut' => 'actif',
            ],
            [
                'email' => 'affiliate3@test.com',
                'nom_complet' => 'Mohamed Tazi',
                'telephone' => '0600000003',
                'statut' => 'inactif',
                'kyc_statut' => 'refuse',
                'profile_statut' => 'suspendu',
            ],
            [
                'email' => 'affiliate4@test.com',
                'nom_complet' => 'Aicha Bennani',
                'telephone' => '0600000004',
                'statut' => 'bloque',
                'kyc_statut' => 'non_requis',
                'profile_statut' => 'suspendu',
            ],
            [
                'email' => 'affiliate5@test.com',
                'nom_complet' => 'Youssef Idrissi',
                'telephone' => '0600000005',
                'statut' => 'actif',
                'kyc_statut' => 'valide',
                'profile_statut' => 'actif',
            ],
        ];

        foreach ($affiliateData as $data) {
            $user = User::firstOrCreate([
                'email' => $data['email'],
            ], [
                'nom_complet' => $data['nom_complet'],
                'email_verifie' => true,
                'mot_de_passe_hash' => bcrypt('password'),
                'telephone' => $data['telephone'],
                'statut' => $data['statut'],
                'kyc_statut' => $data['kyc_statut'],
            ]);

            $user->assignRole('affiliate');

            // Create affiliate profile
            ProfilAffilie::firstOrCreate([
                'utilisateur_id' => $user->id,
            ], [
                'gamme_id' => $gamme->id,
                'points' => 0, // Start with 0 points for clean testing
                'statut' => $data['profile_statut'],
                'rib' => rand(0, 1) ? 'RIB' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT) : null,
            ]);

            $this->command->info('Created affiliate profile for: ' . $user->email);
        }
    }
}
