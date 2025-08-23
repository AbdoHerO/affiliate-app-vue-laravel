<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Faker\Factory as Faker;

class SimpleReferralSeeder extends Seeder
{
    private $faker;
    
    public function run(): void
    {
        $this->command->info('🔗 Seeding Simple Referral System Dataset...');
        
        // Initialize faker with fixed seed for reproducible results
        $this->faker = Faker::create();
        $this->faker->seed(12345);
        
        // Get existing affiliates
        $affiliates = User::role('affiliate')->take(10)->get();
        
        if ($affiliates->count() < 5) {
            $this->command->error('Need at least 5 affiliates to run this seeder');
            return;
        }
        
        $this->command->info("Using {$affiliates->count()} affiliates");
        
        // Create referral codes for each affiliate
        $referralCodes = [];
        foreach ($affiliates as $affiliate) {
            $profile = $affiliate->profilAffilie;
            if ($profile) {
                $code = ReferralCode::getOrCreateForAffiliate($profile);
                $referralCodes[] = $code;
            }
        }
        
        $this->command->info("Created " . count($referralCodes) . " referral codes");
        
        // Create clicks
        $clicksData = [];
        foreach ($referralCodes as $code) {
            $clickCount = $this->faker->numberBetween(50, 200);
            
            for ($i = 0; $i < $clickCount; $i++) {
                $clicksData[] = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'referral_code' => $code->code,
                    'ip_hash' => hash('sha256', $this->faker->ipv4()),
                    'user_agent' => $this->faker->userAgent(),
                    'referer_url' => $this->faker->randomElement([
                        'https://google.com',
                        'https://facebook.com',
                        'https://instagram.com',
                        null,
                    ]),
                    'clicked_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        
        // Insert clicks in chunks
        $chunks = array_chunk($clicksData, 100);
        foreach ($chunks as $chunk) {
            ReferralClick::insert($chunk);
        }
        
        $this->command->info("Created " . count($clicksData) . " clicks");
        
        // Create some attributions (signups)
        $attributionsData = [];
        $totalAttributions = 0;
        
        foreach ($referralCodes as $code) {
            $attributionCount = $this->faker->numberBetween(5, 20);
            
            for ($i = 0; $i < $attributionCount; $i++) {
                // Create a new user
                $newUser = User::create([
                    'nom_complet' => $this->faker->name() . ' ' . $totalAttributions,
                    'email' => 'referred' . time() . $totalAttributions . '@example.com',
                    'email_verifie' => $this->faker->boolean(70),
                    'mot_de_passe_hash' => Hash::make('password'),
                    'telephone' => $this->faker->phoneNumber(),
                    'adresse' => $this->faker->streetAddress(),
                    'statut' => 'actif',
                    'kyc_statut' => 'non_requis',
                    'approval_status' => 'pending_approval',
                ]);
                
                $attributionsData[] = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'referrer_affiliate_id' => $code->affiliate_id,
                    'referral_code' => $code->code,
                    'new_user_id' => $newUser->id,
                    'ip_hash' => hash('sha256', $this->faker->ipv4()),
                    'attributed_at' => $newUser->created_at,
                    'verified' => $newUser->email_verifie,
                    'verified_at' => $newUser->email_verifie ? $newUser->created_at : null,
                    'source' => 'web',
                    'created_at' => $newUser->created_at,
                    'updated_at' => $newUser->updated_at,
                ];
                
                $totalAttributions++;
            }
        }
        
        // Insert attributions in chunks
        $chunks = array_chunk($attributionsData, 50);
        foreach ($chunks as $chunk) {
            ReferralAttribution::insert($chunk);
        }
        
        $this->command->info("Created {$totalAttributions} attributions");
        
        // Create some dispensations
        $admin = User::role('admin')->first();
        if (!$admin) {
            $this->command->info('No admin found, skipping dispensations');
        } else {
            $dispensationsData = [];
            $totalDispensations = 0;
            
            foreach ($affiliates as $affiliate) {
                $profile = $affiliate->profilAffilie;
                if ($profile) {
                    $dispensationCount = $this->faker->numberBetween(1, 5);
                    
                    for ($i = 0; $i < $dispensationCount; $i++) {
                        $dispensationsData[] = [
                            'id' => \Illuminate\Support\Str::uuid(),
                            'referrer_affiliate_id' => $profile->id,
                            'created_by_admin_id' => $admin->id,
                            'points' => $this->faker->numberBetween(10, 100),
                            'comment' => 'Test reward ' . $totalDispensations,
                            'reference' => 'REF-' . strtoupper($this->faker->bothify('######')),
                            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
                            'updated_at' => now(),
                        ];
                        
                        $totalDispensations++;
                    }
                }
            }
            
            // Insert dispensations
            if (!empty($dispensationsData)) {
                ReferralDispensation::insert($dispensationsData);
                $this->command->info("Created {$totalDispensations} dispensations");
            }
        }
        
        $this->command->info('🎉 Simple Referral System seeding completed!');
        $this->printSummary();
    }
    
    private function printSummary(): void
    {
        $this->command->info("Summary:");
        $this->command->info("  - Referral codes: " . ReferralCode::count());
        $this->command->info("  - Clicks: " . ReferralClick::count());
        $this->command->info("  - Attributions: " . ReferralAttribution::count());
        $this->command->info("  - Verified Attributions: " . ReferralAttribution::whereNotNull('verified_at')->count());
        $this->command->info("  - Dispensations: " . ReferralDispensation::count());
        $this->command->info("  - Total Points Distributed: " . ReferralDispensation::sum('points'));
    }
}
