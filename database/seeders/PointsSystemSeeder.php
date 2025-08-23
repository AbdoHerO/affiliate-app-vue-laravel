<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ProfilAffilie;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use App\Services\PointsService;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PointsSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎯 Seeding Points System...');

        // Create admin user if not exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@cod.test'],
            [
                'nom_complet' => 'Admin User',
                'mot_de_passe_hash' => Hash::make('password'),
                'email_verifie' => true,
                'statut' => 'actif',
                'kyc_statut' => 'non_requis',
                'approval_status' => 'approved',
            ]
        );
        $admin->assignRole('admin');

        // Create 50 affiliate users with varying points scenarios
        $affiliateScenarios = [
            // High earners with various dispensation patterns
            ['earned_range' => [5000, 10000], 'dispensation_count' => [3, 6], 'dispensation_range' => [500, 2000]],
            ['earned_range' => [3000, 5000], 'dispensation_count' => [2, 4], 'dispensation_range' => [300, 1500]],
            ['earned_range' => [1000, 3000], 'dispensation_count' => [1, 3], 'dispensation_range' => [200, 1000]],
            ['earned_range' => [500, 1000], 'dispensation_count' => [0, 2], 'dispensation_range' => [100, 500]],
            ['earned_range' => [100, 500], 'dispensation_count' => [0, 1], 'dispensation_range' => [50, 200]],
        ];

        $pointsService = new PointsService();
        $createdAffiliates = 0;

        foreach ($affiliateScenarios as $scenarioIndex => $scenario) {
            $affiliatesInScenario = $scenarioIndex === 0 ? 5 : 10; // 5 high earners, 10 each for others

            for ($i = 0; $i < $affiliatesInScenario; $i++) {
                $createdAffiliates++;
                
                // Create affiliate user
                $affiliate = User::create([
                    'nom_complet' => "Affiliate {$createdAffiliates}",
                    'email' => "affiliate{$createdAffiliates}@cod.test",
                    'mot_de_passe_hash' => Hash::make('password'),
                    'email_verifie' => true,
                    'statut' => 'actif',
                    'kyc_statut' => 'non_requis',
                    'approval_status' => 'approved',
                ]);
                $affiliate->assignRole('affiliate');

                // Create affiliate profile
                $profilAffilie = ProfilAffilie::create([
                    'utilisateur_id' => $affiliate->id,
                    'gamme_id' => 1, // Assuming default tier exists
                    'statut' => 'actif',
                    'date_activation' => now(),
                ]);

                // Create referral code
                $referralCode = ReferralCode::create([
                    'affiliate_id' => $profilAffilie->id,
                    'code' => 'REF' . str_pad($createdAffiliates, 4, '0', STR_PAD_LEFT),
                    'is_active' => true,
                ]);

                // Generate clicks and attributions to reach target earned points
                $targetEarned = rand($scenario['earned_range'][0], $scenario['earned_range'][1]);
                $this->generateReferralActivity($profilAffilie, $referralCode, $targetEarned);

                // Create dispensations
                $dispensationCount = rand($scenario['dispensation_count'][0], $scenario['dispensation_count'][1]);
                if ($dispensationCount > 0) {
                    $currentBalance = $pointsService->calculateBalance($profilAffilie);
                    $this->createDispensations($profilAffilie, $admin, $dispensationCount, $scenario['dispensation_range'], $currentBalance);
                }

                if ($createdAffiliates % 10 === 0) {
                    $this->command->info("✅ Created {$createdAffiliates} affiliates...");
                }
            }
        }

        // Create some specific test cases
        $this->createSpecificTestCases($admin, $pointsService, $createdAffiliates);

        $this->command->info("🎉 Points System seeded successfully! Created {$createdAffiliates} affiliates with realistic data.");
    }

    /**
     * Generate referral activity to reach target earned points
     */
    private function generateReferralActivity(ProfilAffilie $affiliate, ReferralCode $referralCode, int $targetEarned): void
    {
        // Calculate how many verified signups we need
        // Each verified signup = 10 (signup) + 50 (verified) = 60 points
        // Each click = 1 point
        
        $verifiedSignupsNeeded = intval($targetEarned * 0.8 / 60); // 80% from verified signups
        $remainingPoints = $targetEarned - ($verifiedSignupsNeeded * 60);
        $regularSignupsNeeded = intval($remainingPoints * 0.5 / 10); // 50% of remaining from regular signups
        $clicksNeeded = max(0, $targetEarned - ($verifiedSignupsNeeded * 60) - ($regularSignupsNeeded * 10));

        // Create clicks
        for ($i = 0; $i < $clicksNeeded; $i++) {
            ReferralClick::create([
                'referral_code_id' => $referralCode->id,
                'ip_hash' => hash('sha256', '192.168.1.' . rand(1, 254)),
                'user_agent_hash' => hash('sha256', 'Mozilla/5.0 Test Browser ' . $i),
                'source' => rand(0, 1) ? 'web' : 'mobile',
                'clicked_at' => Carbon::now()->subDays(rand(1, 90)),
            ]);
        }

        // Create regular signups
        for ($i = 0; $i < $regularSignupsNeeded; $i++) {
            $newUser = User::create([
                'nom_complet' => "Referred User " . uniqid(),
                'email' => "referred" . uniqid() . "@example.com",
                'mot_de_passe_hash' => Hash::make('password'),
                'email_verifie' => false,
                'statut' => 'actif',
                'kyc_statut' => 'non_requis',
                'approval_status' => 'pending_approval',
            ]);

            ReferralAttribution::create([
                'referral_code' => $referralCode->code,
                'referrer_affiliate_id' => $affiliate->id,
                'new_user_id' => $newUser->id,
                'ip_hash' => hash('sha256', '192.168.1.' . rand(1, 254)),
                'source' => 'web',
                'verified' => false,
                'attributed_at' => Carbon::now()->subDays(rand(1, 60)),
            ]);
        }

        // Create verified signups
        for ($i = 0; $i < $verifiedSignupsNeeded; $i++) {
            $newUser = User::create([
                'nom_complet' => "Verified User " . uniqid(),
                'email' => "verified" . uniqid() . "@example.com",
                'mot_de_passe_hash' => Hash::make('password'),
                'email_verifie' => true,
                'statut' => 'actif',
                'kyc_statut' => 'non_requis',
                'approval_status' => 'approved',
            ]);

            ReferralAttribution::create([
                'referral_code' => $referralCode->code,
                'referrer_affiliate_id' => $affiliate->id,
                'new_user_id' => $newUser->id,
                'ip_hash' => hash('sha256', '192.168.1.' . rand(1, 254)),
                'source' => 'web',
                'verified' => true,
                'attributed_at' => Carbon::now()->subDays(rand(1, 60)),
                'verified_at' => Carbon::now()->subDays(rand(1, 30)),
            ]);
        }
    }

    /**
     * Create dispensations for an affiliate
     */
    private function createDispensations(ProfilAffilie $affiliate, User $admin, int $count, array $range, int $maxBalance): void
    {
        $comments = [
            'Téléphone offert',
            'Laptop cadeau',
            'Bon d'achat Amazon',
            'Récompense performance',
            'Prime de parrainage',
            'Cadeau de fin d'année',
            'Bonus spécial',
            'Récompense fidélité',
        ];

        $remainingBalance = $maxBalance;

        for ($i = 0; $i < $count && $remainingBalance > 0; $i++) {
            $maxDispensation = min($remainingBalance, $range[1]);
            $minDispensation = min($range[0], $maxDispensation);
            
            if ($maxDispensation <= 0) break;

            $points = rand($minDispensation, $maxDispensation);
            
            ReferralDispensation::create([
                'referrer_affiliate_id' => $affiliate->id,
                'points' => $points,
                'comment' => $comments[array_rand($comments)],
                'reference' => 'REF-' . time() . '-' . $i,
                'created_by_admin_id' => $admin->id,
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
            ]);

            $remainingBalance -= $points;
        }
    }

    /**
     * Create specific test cases for edge scenarios
     */
    private function createSpecificTestCases(User $admin, PointsService $pointsService, int &$createdAffiliates): void
    {
        // Test case 1: Affiliate with exact 100 points balance
        $this->createTestAffiliate($admin, $pointsService, $createdAffiliates, 'Test 100 Balance', 100, 0);
        
        // Test case 2: Affiliate with exact 500 points balance
        $this->createTestAffiliate($admin, $pointsService, $createdAffiliates, 'Test 500 Balance', 500, 0);
        
        // Test case 3: Affiliate with 0 balance (all dispensed)
        $this->createTestAffiliate($admin, $pointsService, $createdAffiliates, 'Test Zero Balance', 1000, 1000);
        
        // Test case 4: High earner with minimal dispensations
        $this->createTestAffiliate($admin, $pointsService, $createdAffiliates, 'High Earner', 10000, 500);
    }

    private function createTestAffiliate(User $admin, PointsService $pointsService, int &$counter, string $name, int $targetEarned, int $targetDispensed): void
    {
        $counter++;
        
        $affiliate = User::create([
            'nom_complet' => $name,
            'email' => "test{$counter}@cod.test",
            'mot_de_passe_hash' => Hash::make('password'),
            'email_verifie' => true,
            'statut' => 'actif',
            'kyc_statut' => 'non_requis',
            'approval_status' => 'approved',
        ]);
        $affiliate->assignRole('affiliate');

        $profilAffilie = ProfilAffilie::create([
            'utilisateur_id' => $affiliate->id,
            'gamme_id' => 1,
            'statut' => 'actif',
            'date_activation' => now(),
        ]);

        $referralCode = ReferralCode::create([
            'affiliate_id' => $profilAffilie->id,
            'code' => 'TEST' . str_pad($counter, 3, '0', STR_PAD_LEFT),
            'is_active' => true,
        ]);

        // Generate activity for target earned points
        $this->generateReferralActivity($profilAffilie, $referralCode, $targetEarned);

        // Create dispensations if needed
        if ($targetDispensed > 0) {
            ReferralDispensation::create([
                'referrer_affiliate_id' => $profilAffilie->id,
                'points' => $targetDispensed,
                'comment' => "Test dispensation for {$name}",
                'reference' => 'TEST-' . time(),
                'created_by_admin_id' => $admin->id,
                'created_at' => Carbon::now()->subDays(5),
            ]);
        }
    }
}
