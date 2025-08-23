<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ProfilAffilie;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Faker\Factory as Faker;

class ReferralSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $this->command->info('🔗 Seeding Referral System...');

        // Get existing affiliates or create some
        $affiliates = ProfilAffilie::with('utilisateur')->get();
        
        if ($affiliates->count() < 5) {
            $this->command->info('Creating additional affiliates for referral testing...');
            $this->createTestAffiliates($faker, 5 - $affiliates->count());
            $affiliates = ProfilAffilie::with('utilisateur')->get();
        }

        // Create referral codes for all affiliates
        $this->command->info('Creating referral codes...');
        $referralCodes = [];
        foreach ($affiliates as $affiliate) {
            $code = ReferralCode::getOrCreateForAffiliate($affiliate);
            $referralCodes[] = $code;
            $this->command->info("  ✓ Created referral code {$code->code} for {$affiliate->utilisateur->nom_complet}");
        }

        // Create referral clicks
        $this->command->info('Creating referral clicks...');
        $totalClicks = 0;
        foreach ($referralCodes as $code) {
            $clickCount = $faker->numberBetween(10, 100);
            for ($i = 0; $i < $clickCount; $i++) {
                ReferralClick::create([
                    'referral_code' => $code->code,
                    'ip_hash' => hash('sha256', $faker->ipv4() . config('app.key')),
                    'user_agent' => $faker->userAgent(),
                    'referer_url' => $faker->optional(0.7)->url(),
                    'device_fingerprint' => [
                        'user_agent' => $faker->userAgent(),
                        'accept_language' => $faker->randomElement(['en-US,en;q=0.9', 'fr-FR,fr;q=0.9', 'ar-SA,ar;q=0.9']),
                        'accept_encoding' => 'gzip, deflate, br',
                    ],
                    'clicked_at' => $faker->dateTimeBetween('-60 days', 'now'),
                ]);
                $totalClicks++;
            }
        }
        $this->command->info("  ✓ Created {$totalClicks} referral clicks");

        // Create referred users and attributions
        $this->command->info('Creating referred users and attributions...');
        $totalAttributions = 0;
        foreach ($referralCodes as $code) {
            $attributionCount = $faker->numberBetween(2, 15);
            for ($i = 0; $i < $attributionCount; $i++) {
                // Create a new user
                $user = User::create([
                    'nom_complet' => $faker->name(),
                    'email' => $faker->unique()->safeEmail(),
                    'mot_de_passe_hash' => Hash::make('password'),
                    'telephone' => $faker->phoneNumber(),
                    'adresse' => $faker->address(),
                    'statut' => 'actif',
                    'email_verifie' => $faker->boolean(80), // 80% verified
                    'kyc_statut' => 'non_requis',
                ]);

                // Assign affiliate role
                $user->assignRole('affiliate');

                // Create attribution
                $attributedAt = $faker->dateTimeBetween('-45 days', 'now');
                $verified = $user->email_verifie;
                
                ReferralAttribution::create([
                    'new_user_id' => $user->id,
                    'referrer_affiliate_id' => $code->affiliate->id,
                    'referral_code' => $code->code,
                    'attributed_at' => $attributedAt,
                    'verified' => $verified,
                    'verified_at' => $verified ? $attributedAt->modify('+' . $faker->numberBetween(1, 24) . ' hours') : null,
                    'source' => $faker->randomElement(['web', 'mobile']),
                    'ip_hash' => hash('sha256', $faker->ipv4() . config('app.key')),
                    'device_fingerprint' => [
                        'user_agent' => $faker->userAgent(),
                        'accept_language' => $faker->randomElement(['en-US,en;q=0.9', 'fr-FR,fr;q=0.9', 'ar-SA,ar;q=0.9']),
                    ],
                ]);
                $totalAttributions++;
            }
        }
        $this->command->info("  ✓ Created {$totalAttributions} referral attributions");

        // Create dispensations (manual rewards)
        $this->command->info('Creating dispensations...');
        $adminUsers = User::role('admin')->get();
        if ($adminUsers->isEmpty()) {
            $this->command->warn('No admin users found. Creating one for dispensations...');
            $admin = User::create([
                'nom_complet' => 'Admin Test',
                'email' => 'admin@test.com',
                'mot_de_passe_hash' => Hash::make('password'),
                'statut' => 'actif',
                'email_verifie' => true,
                'kyc_statut' => 'non_requis',
            ]);
            $admin->assignRole('admin');
            $adminUsers = collect([$admin]);
        }

        $totalDispensations = 0;
        foreach ($affiliates as $affiliate) {
            // Some affiliates get dispensations, others don't
            if ($faker->boolean(70)) {
                $dispensationCount = $faker->numberBetween(1, 5);
                for ($i = 0; $i < $dispensationCount; $i++) {
                    ReferralDispensation::create([
                        'referrer_affiliate_id' => $affiliate->id,
                        'points' => $faker->numberBetween(10, 500),
                        'comment' => $faker->randomElement([
                            'Bonus pour performance exceptionnelle',
                            'Récompense campagne été 2024',
                            'Prime de fidélité',
                            'Bonus top referrer du mois',
                            'Récompense spéciale parrainage',
                            'Prime d\'encouragement',
                        ]),
                        'reference' => $faker->optional(0.6)->randomElement([
                            'SUMMER2024',
                            'TOP_REF_' . date('Y_m'),
                            'LOYALTY_BONUS',
                            'SPECIAL_CAMPAIGN',
                        ]),
                        'created_by_admin_id' => $adminUsers->random()->id,
                        'created_at' => $faker->dateTimeBetween('-30 days', 'now'),
                    ]);
                    $totalDispensations++;
                }
            }
        }
        $this->command->info("  ✓ Created {$totalDispensations} dispensations");

        $this->command->info('🎉 Referral System seeding completed!');
        $this->command->info("Summary:");
        $this->command->info("  - Referral codes: " . count($referralCodes));
        $this->command->info("  - Clicks: {$totalClicks}");
        $this->command->info("  - Attributions: {$totalAttributions}");
        $this->command->info("  - Dispensations: {$totalDispensations}");
    }

    /**
     * Create test affiliates if needed.
     */
    private function createTestAffiliates($faker, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = User::create([
                'nom_complet' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'mot_de_passe_hash' => Hash::make('password'),
                'telephone' => $faker->phoneNumber(),
                'adresse' => $faker->address(),
                'statut' => 'actif',
                'email_verifie' => true,
                'kyc_statut' => 'valide',
                'approval_status' => 'approved',
            ]);

            $user->assignRole('affiliate');

            // Create affiliate profile
            ProfilAffilie::create([
                'utilisateur_id' => $user->id,
                'gamme_id' => \App\Models\GammeAffilie::first()?->id,
                'points' => $faker->numberBetween(0, 1000),
                'statut' => 'actif',
            ]);
        }
    }
}
