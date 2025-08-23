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

class LargeReferralSystemSeeder extends Seeder
{
    private $faker;
    private $startDate;
    private $endDate;
    private $affiliates = [];
    private $referralCodes = [];
    
    public function run(): void
    {
        $this->command->info('🔗 Seeding Large Referral System Dataset...');
        
        // Initialize faker with fixed seed for reproducible results
        $this->faker = Faker::create();
        $this->faker->seed(12345);
        
        // Set date range (last 90 days)
        $this->endDate = Carbon::now();
        $this->startDate = Carbon::now()->subDays(90);
        
        $this->ensureDefaultGamme();
        $this->createAffiliates();
        $this->createReferralCodes();
        $this->createReferralClicks();
        $this->createReferralAttributions();
        $this->createDispensations();
        
        $this->command->info('🎉 Large Referral System seeding completed!');
        $this->printSummary();
    }

    private function ensureDefaultGamme(): void
    {
        // Create default gamme if it doesn't exist
        $defaultGamme = \App\Models\GammeAffilie::first();
        if (!$defaultGamme) {
            $defaultGamme = \App\Models\GammeAffilie::create([
                'code' => 'STANDARD',
                'libelle' => 'Standard',
                'actif' => true,
            ]);
            $this->command->info('  ✓ Created default gamme: ' . $defaultGamme->libelle);
        }
    }
    
    private function createAffiliates(): void
    {
        $this->command->info('Using existing affiliates and creating additional ones if needed...');

        // Get existing affiliates
        $existingAffiliates = User::role('affiliate')->get();
        $this->affiliates = $existingAffiliates->all();

        // Ensure we have at least 20 affiliates for a good test
        $needed = max(0, 20 - count($this->affiliates));

        if ($needed > 0) {
            $this->command->info("Creating {$needed} additional affiliates...");

            for ($i = 0; $i < $needed; $i++) {
                $affiliate = User::create([
                    'nom_complet' => $this->faker->name() . ' ' . $i, // Add index to avoid duplicates
                    'email' => 'affiliate' . ($i + 100) . '@example.com', // Use sequential emails
                    'email_verifie' => $this->faker->boolean(80),
                    'mot_de_passe_hash' => Hash::make('password'),
                    'telephone' => $this->faker->phoneNumber(),
                    'adresse' => $this->faker->streetAddress(),
                    'statut' => 'actif',
                    'kyc_statut' => $this->faker->randomElement(['valide', 'en_attente', 'refuse']),
                    'approval_status' => $this->faker->randomElement(['approved', 'pending_approval', 'refused']),
                    'created_at' => $this->faker->dateTimeBetween($this->startDate, $this->endDate),
                    'updated_at' => $this->faker->dateTimeBetween($this->startDate, $this->endDate),
                ]);

                $affiliate->assignRole('affiliate');
                $this->affiliates[] = $affiliate;
            }
        }

        $this->command->info("  ✓ Using " . count($this->affiliates) . " affiliates");
    }
    
    private function createReferralCodes(): void
    {
        $this->command->info('Creating referral codes...');
        
        foreach ($this->affiliates as $affiliate) {
            // Get or create affiliate profile
            $affiliateProfile = $affiliate->profilAffilie;
            if (!$affiliateProfile) {
                // Create affiliate profile if it doesn't exist
                $defaultGamme = \App\Models\GammeAffilie::first();
                $affiliateProfile = \App\Models\ProfilAffilie::create([
                    'utilisateur_id' => $affiliate->id,
                    'gamme_id' => $defaultGamme->id,
                    'statut' => 'actif',
                    'points' => 0,
                ]);
            }

            $code = ReferralCode::getOrCreateForAffiliate($affiliateProfile);
            
            $this->referralCodes[] = $code;
        }
        
        $this->command->info("  ✓ Created " . count($this->referralCodes) . " referral codes");
    }
    


    private function createReferralClicks(): void
    {
        $this->command->info('Creating ~10,000 referral clicks...');

        $totalClicks = 0;
        $clicksData = [];

        // Create weighted distribution (top 10 affiliates get 60% of clicks)
        $topAffiliates = array_slice($this->referralCodes, 0, 10);
        $regularAffiliates = array_slice($this->referralCodes, 10);

        // Generate clicks for top affiliates (60% of total)
        foreach ($topAffiliates as $code) {
            $clickCount = $this->faker->numberBetween(400, 800);
            $totalClicks += $clickCount;

            for ($i = 0; $i < $clickCount; $i++) {
                $clicksData[] = $this->generateClickData($code);
            }
        }

        // Generate clicks for regular affiliates (40% of total)
        foreach ($regularAffiliates as $code) {
            $clickCount = $this->faker->numberBetween(50, 200);
            $totalClicks += $clickCount;

            for ($i = 0; $i < $clickCount; $i++) {
                $clicksData[] = $this->generateClickData($code);
            }
        }

        // Insert in chunks to avoid memory issues
        $chunks = array_chunk($clicksData, 500);
        foreach ($chunks as $chunk) {
            ReferralClick::insert($chunk);
        }

        $this->command->info("  ✓ Created {$totalClicks} referral clicks");
    }

    private function generateClickData($referralCode): array
    {
        // Generate realistic timestamp with business hour bias
        $timestamp = $this->getRealisticTimestamp();

        // 20% chance of repeat visitor (same IP/UA combination)
        $isRepeatVisitor = $this->faker->boolean(20);

        if ($isRepeatVisitor && !empty($this->usedIpUserAgents)) {
            $existing = $this->faker->randomElement($this->usedIpUserAgents);
            $ipHash = $existing['ip_hash'];
            $userAgent = $existing['user_agent'];
        } else {
            $ipHash = hash('sha256', $this->faker->ipv4());
            $userAgent = $this->faker->userAgent();

            $this->usedIpUserAgents[] = [
                'ip_hash' => $ipHash,
                'user_agent' => $userAgent,
            ];
        }

        return [
            'id' => \Illuminate\Support\Str::uuid(),
            'referral_code' => $referralCode->code,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent,
            'referer_url' => $this->faker->randomElement([
                'https://google.com',
                'https://facebook.com',
                'https://instagram.com',
                'https://twitter.com',
                null,
            ]),
            'clicked_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private $usedIpUserAgents = [];

    private function getRealisticTimestamp(): Carbon
    {
        // Generate timestamp with business hour bias (more activity during weekdays 9-17)
        $date = $this->faker->dateTimeBetween($this->startDate, $this->endDate);
        $carbon = Carbon::instance($date);

        // 70% chance of weekday, 30% weekend
        if ($this->faker->boolean(70)) {
            // Weekday - bias towards business hours
            $carbon = $carbon->setWeekday($this->faker->numberBetween(1, 5));

            if ($this->faker->boolean(60)) {
                // Business hours (9 AM - 5 PM)
                $carbon = $carbon->setHour($this->faker->numberBetween(9, 17));
            } else {
                // Other hours
                $carbon = $carbon->setHour($this->faker->numberBetween(0, 23));
            }
        } else {
            // Weekend
            $carbon = $carbon->setWeekday($this->faker->numberBetween(6, 7));
            $carbon = $carbon->setHour($this->faker->numberBetween(10, 22));
        }

        $carbon = $carbon->setMinute($this->faker->numberBetween(0, 59));
        $carbon = $carbon->setSecond($this->faker->numberBetween(0, 59));

        return $carbon;
    }

    private function createReferralAttributions(): void
    {
        $this->command->info('Creating ~2,500 referral attributions...');

        // Get unique clicks (one per IP/UA combination for conversion)
        $uniqueClicks = ReferralClick::select('referral_code', 'ip_hash', 'user_agent')
            ->groupBy('referral_code', 'ip_hash', 'user_agent')
            ->get();

        $attributionsData = [];
        $totalAttributions = 0;

        // Convert ~25% of unique clicks to signups
        foreach ($uniqueClicks as $click) {
            if ($this->faker->boolean(25)) {
                // Create a new user for this signup
                $newUser = User::create([
                    'nom_complet' => $this->faker->name(),
                    'email' => $this->faker->unique()->safeEmail(),
                    'email_verifie' => $this->faker->boolean(70),
                    'mot_de_passe_hash' => Hash::make('password'),
                    'telephone' => $this->faker->phoneNumber(),
                    'adresse' => $this->faker->streetAddress(),
                    'statut' => 'actif',
                    'kyc_statut' => 'non_requis',
                    'approval_status' => 'pending_approval',
                    'created_at' => $this->faker->dateTimeBetween($this->startDate, $this->endDate),
                    'updated_at' => $this->faker->dateTimeBetween($this->startDate, $this->endDate),
                ]);

                // Find the referral code record
                $referralCodeRecord = ReferralCode::where('code', $click->referral_code)->first();

                $attributionsData[] = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'referral_code_id' => $referralCodeRecord->id,
                    'new_user_id' => $newUser->id,
                    'ip_hash' => $click->ip_hash,
                    'user_agent' => $click->user_agent,
                    'attributed_at' => $newUser->created_at,
                    'verified_at' => $newUser->email_verifie ? $newUser->created_at : null,
                    'created_at' => $newUser->created_at,
                    'updated_at' => $newUser->updated_at,
                ];

                $totalAttributions++;
            }
        }

        // Insert in chunks
        $chunks = array_chunk($attributionsData, 100);
        foreach ($chunks as $chunk) {
            ReferralAttribution::insert($chunk);
        }

        $this->command->info("  ✓ Created {$totalAttributions} referral attributions");
    }

    private function createDispensations(): void
    {
        $this->command->info('Creating ~600 dispensations...');

        // Get admin user for dispensations
        $admin = User::role('admin')->first();
        if (!$admin) {
            $admin = User::create([
                'nom_complet' => 'Admin User',
                'email' => 'admin@example.com',
                'email_verifie' => true,
                'mot_de_passe_hash' => Hash::make('password'),
                'telephone' => '+1234567890',
                'adresse' => '123 Admin Street',
                'statut' => 'actif',
                'kyc_statut' => 'valide',
                'approval_status' => 'approved',
            ]);
            $admin->assignRole('admin');
        }

        $dispensationsData = [];
        $totalDispensations = 0;

        // Focus dispensations on top performing affiliates
        $topAffiliates = array_slice($this->affiliates, 0, 20);

        foreach ($topAffiliates as $affiliate) {
            $dispensationCount = $this->faker->numberBetween(15, 50);

            for ($i = 0; $i < $dispensationCount; $i++) {
                $points = $this->faker->numberBetween(10, 100);
                $comments = [
                    'Campaign bonus - Week ' . $this->faker->numberBetween(1, 52),
                    'Manual gift for excellent performance',
                    'Referral milestone reward',
                    'Special promotion bonus',
                    'Quality referral bonus',
                    'Monthly performance reward',
                    'Holiday bonus',
                    'Anniversary reward',
                ];

                $dispensationsData[] = [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'affiliate_id' => $affiliate->profilAffilie->id,
                    'admin_id' => $admin->id,
                    'points' => $points,
                    'comment' => $this->faker->randomElement($comments),
                    'reference' => 'REF-' . strtoupper($this->faker->bothify('######')),
                    'created_at' => $this->faker->dateTimeBetween($this->startDate, $this->endDate),
                    'updated_at' => $this->faker->dateTimeBetween($this->startDate, $this->endDate),
                ];

                $totalDispensations++;
            }
        }

        // Add some dispensations for regular affiliates too
        $regularAffiliates = array_slice($this->affiliates, 20);
        foreach ($regularAffiliates as $affiliate) {
            if ($this->faker->boolean(40)) { // 40% chance
                $dispensationCount = $this->faker->numberBetween(1, 8);

                for ($i = 0; $i < $dispensationCount; $i++) {
                    $points = $this->faker->numberBetween(5, 50);

                    $dispensationsData[] = [
                        'id' => \Illuminate\Support\Str::uuid(),
                        'affiliate_id' => $affiliate->profilAffilie->id,
                        'admin_id' => $admin->id,
                        'points' => $points,
                        'comment' => 'Welcome bonus',
                        'reference' => 'REF-' . strtoupper($this->faker->bothify('######')),
                        'created_at' => $this->faker->dateTimeBetween($this->startDate, $this->endDate),
                        'updated_at' => $this->faker->dateTimeBetween($this->startDate, $this->endDate),
                    ];

                    $totalDispensations++;
                }
            }
        }

        // Insert in chunks
        $chunks = array_chunk($dispensationsData, 100);
        foreach ($chunks as $chunk) {
            ReferralDispensation::insert($chunk);
        }

        $this->command->info("  ✓ Created {$totalDispensations} dispensations");
    }

    private function printSummary(): void
    {
        $this->command->info("Summary:");
        $this->command->info("  - Affiliates: " . count($this->affiliates));
        $this->command->info("  - Referral codes: " . ReferralCode::count());
        $this->command->info("  - Clicks: " . ReferralClick::count());
        $this->command->info("  - Attributions: " . ReferralAttribution::count());
        $this->command->info("  - Verified Attributions: " . ReferralAttribution::whereNotNull('verified_at')->count());
        $this->command->info("  - Dispensations: " . ReferralDispensation::count());
        $this->command->info("  - Total Points Distributed: " . ReferralDispensation::sum('points'));

        $conversionRate = ReferralClick::count() > 0
            ? round((ReferralAttribution::count() / ReferralClick::count()) * 100, 2)
            : 0;
        $this->command->info("  - Overall Conversion Rate: {$conversionRate}%");
    }
}
