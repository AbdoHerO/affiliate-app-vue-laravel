<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use App\Models\ProfilAffilie;
use App\Models\GammeAffilie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class CODReferralSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔗 Seeding COD Referral System for your test users...');

        $faker = Faker::create();
        $faker->seed(12345); // Fixed seed for reproducible results

        // Find your test users
        $admin = User::where('email', 'admin@cod.test')->first();
        $affiliate = User::where('email', 'affiliate@cod.test')->first();

        if (!$admin) {
            $this->command->warn('⚠️ admin@cod.test not found, skipping referral seeding');
            return;
        }

        if (!$affiliate) {
            $this->command->warn('⚠️ affiliate@cod.test not found, skipping referral seeding');
            return;
        }

        // Ensure affiliate has a profile
        $affiliateProfile = $affiliate->profilAffilie;
        if (!$affiliateProfile) {
            // Create default gamme if needed
            $defaultGamme = GammeAffilie::first();
            if (!$defaultGamme) {
                $defaultGamme = GammeAffilie::create([
                    'code' => 'STANDARD',
                    'libelle' => 'Standard',
                    'actif' => true,
                ]);
                $this->command->info('  ✓ Created default gamme');
            }
            
            $affiliateProfile = ProfilAffilie::create([
                'utilisateur_id' => $affiliate->id,
                'gamme_id' => $defaultGamme->id,
                'statut' => 'actif',
                'points' => 0,
            ]);
            $this->command->info('  ✓ Created affiliate profile for affiliate@cod.test');
        }

        // Create referral code for your test affiliate
        $referralCode = ReferralCode::getOrCreateForAffiliate($affiliateProfile);
        $this->command->info("  ✓ Referral code: {$referralCode->code} for affiliate@cod.test");

        // Create realistic test clicks (300-600 clicks)
        $clickCount = $faker->numberBetween(300, 600);
        $clicksData = [];
        
        for ($i = 0; $i < $clickCount; $i++) {
            $clicksData[] = [
                'id' => \Illuminate\Support\Str::uuid(),
                'referral_code' => $referralCode->code,
                'ip_hash' => hash('sha256', $faker->ipv4()),
                'user_agent' => $faker->userAgent(),
                'referer_url' => $faker->randomElement([
                    'https://google.com/search?q=cod+maroc',
                    'https://facebook.com',
                    'https://instagram.com',
                    'https://twitter.com',
                    'https://tiktok.com',
                    null,
                ]),
                'clicked_at' => $faker->dateTimeBetween('-45 days', 'now'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert clicks in chunks
        $chunks = array_chunk($clicksData, 100);
        foreach ($chunks as $chunk) {
            ReferralClick::insert($chunk);
        }

        $this->command->info("  ✓ Created {$clickCount} clicks");

        // Create attributions (signups) - about 15-25% conversion rate
        $attributionCount = $faker->numberBetween(45, 150);
        $attributionsData = [];
        
        for ($i = 0; $i < $attributionCount; $i++) {
            // Create a new user for this signup
            $newUser = User::create([
                'nom_complet' => $faker->name() . ' (Parrainé)',
                'email' => 'parraine_' . time() . '_' . $i . '@example.com',
                'email_verifie' => $faker->boolean(80), // 80% verified
                'mot_de_passe_hash' => Hash::make('password'),
                'telephone' => $faker->phoneNumber(),
                'adresse' => $faker->streetAddress(),
                'statut' => 'actif',
                'kyc_statut' => 'non_requis',
                'approval_status' => 'pending_approval',
            ]);

            $attributionsData[] = [
                'id' => \Illuminate\Support\Str::uuid(),
                'referrer_affiliate_id' => $affiliateProfile->id,
                'referral_code' => $referralCode->code,
                'new_user_id' => $newUser->id,
                'ip_hash' => hash('sha256', $faker->ipv4()),
                'attributed_at' => $newUser->created_at,
                'verified' => $newUser->email_verifie,
                'verified_at' => $newUser->email_verifie ? $newUser->created_at : null,
                'source' => 'web',
                'created_at' => $newUser->created_at,
                'updated_at' => $newUser->updated_at,
            ];
        }

        // Insert attributions in chunks
        $chunks = array_chunk($attributionsData, 50);
        foreach ($chunks as $chunk) {
            ReferralAttribution::insert($chunk);
        }

        $this->command->info("  ✓ Created {$attributionCount} attributions");

        // Create dispensations (manual rewards)
        $dispensationCount = $faker->numberBetween(8, 20);
        $dispensationsData = [];
        
        for ($i = 0; $i < $dispensationCount; $i++) {
            $dispensationsData[] = [
                'id' => \Illuminate\Support\Str::uuid(),
                'referrer_affiliate_id' => $affiliateProfile->id,
                'created_by_admin_id' => $admin->id,
                'points' => $faker->numberBetween(50, 300),
                'comment' => $faker->randomElement([
                    'Bonus de campagne - Excellent travail!',
                    'Récompense manuelle pour performance exceptionnelle',
                    'Bonus de parrainage - Objectif mensuel atteint',
                    'Promotion spéciale - Merci pour votre engagement',
                    'Bonus de fidélité - Client VIP référé',
                    'Récompense trimestrielle - Top performer',
                    'Bonus de lancement - Nouveau produit',
                    'Cadeau de remerciement - Partenaire privilégié',
                ]),
                'reference' => 'COD-' . strtoupper($faker->bothify('######')),
                'created_at' => $faker->dateTimeBetween('-30 days', 'now'),
                'updated_at' => now(),
            ];
        }

        // Insert dispensations
        ReferralDispensation::insert($dispensationsData);

        // Calculate totals for summary
        $totalPoints = array_sum(array_column($dispensationsData, 'points'));
        $verifiedCount = count(array_filter($attributionsData, fn($attr) => $attr['verified']));
        $conversionRate = $clickCount > 0 ? round(($attributionCount / $clickCount) * 100, 2) : 0;

        $this->command->info('🎉 COD Referral System seeding completed!');
        $this->command->info("📊 Summary for affiliate@cod.test:");
        $this->command->info("  - Referral code: {$referralCode->code}");
        $this->command->info("  - Clicks: {$clickCount}");
        $this->command->info("  - Attributions: {$attributionCount}");
        $this->command->info("  - Verified: {$verifiedCount}");
        $this->command->info("  - Conversion rate: {$conversionRate}%");
        $this->command->info("  - Dispensations: {$dispensationCount}");
        $this->command->info("  - Total points: {$totalPoints}");
        $this->command->info("🔗 Referral URL: http://localhost:8000/signup?ref={$referralCode->code}");
    }
}
