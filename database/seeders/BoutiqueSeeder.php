<?php

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BoutiqueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing users to be boutique owners (preferably affiliates)
        $owners = User::role('affiliate')->limit(3)->get();
        
        // If we don't have enough affiliate users, get any available users
        if ($owners->count() < 3) {
            $additionalOwners = User::whereNotIn('id', $owners->pluck('id'))
                ->limit(3 - $owners->count())
                ->get();
            $owners = $owners->merge($additionalOwners);
        }
        
        if ($owners->count() === 0) {
            // Create some default users to be boutique owners
            $userData = [
                [
                    'nom_complet' => 'Jean Dupont',
                    'email' => 'jean.dupont@example.com',
                    'mot_de_passe_hash' => bcrypt('password'),
                    'statut' => 'actif',
                    'email_verifie' => true,
                ],
                [
                    'nom_complet' => 'Marie Martin',
                    'email' => 'marie.martin@example.com',
                    'mot_de_passe_hash' => bcrypt('password'),
                    'statut' => 'actif',
                    'email_verifie' => true,
                ],
                [
                    'nom_complet' => 'Ahmed Benali',
                    'email' => 'ahmed.benali@example.com',
                    'mot_de_passe_hash' => bcrypt('password'),
                    'statut' => 'actif',
                    'email_verifie' => true,
                ],
            ];
            
            $owners = collect();
            foreach ($userData as $data) {
                $user = User::create($data);
                $user->assignRole('affiliate'); // Assign affiliate role
                $owners->push($user);
            }
        }

        // Default boutiques data
        $boutiques = [
            [
                'nom' => 'TechStore Pro',
                'slug' => 'techstore-pro',
                'adresse' => '123 Avenue des Champs-Élysées, 75008 Paris, France',
                'email_pro' => 'contact@techstore-pro.com',
                'statut' => 'actif',
                'commission_par_defaut' => 8.5,
            ],
            [
                'nom' => 'Fashion Boutique',
                'slug' => 'fashion-boutique',
                'adresse' => '45 Rue de Rivoli, 75001 Paris, France',
                'email_pro' => 'info@fashion-boutique.com',
                'statut' => 'actif',
                'commission_par_defaut' => 12.0,
            ],
            [
                'nom' => 'Maison & Jardin',
                'slug' => 'maison-jardin',
                'adresse' => '78 Boulevard Saint-Germain, 75005 Paris, France',
                'email_pro' => 'contact@maison-jardin.com',
                'statut' => 'actif',
                'commission_par_defaut' => 6.5,
            ],
            [
                'nom' => 'Sports Attitude',
                'slug' => 'sports-attitude',
                'adresse' => '156 Rue de la Paix, 69002 Lyon, France',
                'email_pro' => 'info@sports-attitude.com',
                'statut' => 'suspendu',
                'commission_par_defaut' => 10.0,
            ],
            [
                'nom' => 'Librairie Moderne',
                'slug' => 'librairie-moderne',
                'adresse' => '89 Cours Mirabeau, 13100 Aix-en-Provence, France',
                'email_pro' => 'contact@librairie-moderne.com',
                'statut' => 'desactive',
                'commission_par_defaut' => 5.0,
            ],
        ];

        foreach ($boutiques as $index => $boutiqueData) {
            // Assign a random owner from our collection
            $owner = $owners->get($index % $owners->count());
            
            Boutique::create(array_merge($boutiqueData, [
                'proprietaire_id' => $owner->id,
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subDays(rand(0, 10)),
            ]));
        }

        $this->command->info('✅ Boutiques seeded successfully!');
    }
}
