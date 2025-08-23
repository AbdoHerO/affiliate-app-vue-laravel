<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\ProfilAffilie;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\ReferralDispensation;
use App\Services\ReferralService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TestReferralSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:referral-system {--reset : Reset test data before running}';

    /**
     * The console command description.
     */
    protected $description = 'Test the complete referral system workflow';

    /**
     * Execute the console command.
     */
    public function handle(ReferralService $referralService)
    {
        $this->info('🔗 Testing Referral System...');

        if ($this->option('reset')) {
            $this->resetTestData();
        }

        // Test 1: Referral Code Generation
        $this->info('📝 Test 1: Referral Code Generation');
        $affiliate = $this->getOrCreateTestAffiliate();
        $referralCode = $referralService->getOrCreateReferralCode($affiliate);
        $this->info("  ✓ Generated referral code: {$referralCode->code}");

        // Test 2: Referral URL Generation
        $this->info('🔗 Test 2: Referral URL Generation');
        $referralUrl = $referralService->generateReferralUrl($affiliate);
        $this->info("  ✓ Generated referral URL: {$referralUrl}");

        // Test 3: Click Tracking
        $this->info('👆 Test 3: Click Tracking');
        $request = $this->createMockRequest(['ref' => $referralCode->code]);
        $tracked = $referralService->trackClick($referralCode->code, $request);
        $this->info($tracked ? "  ✓ Click tracked successfully" : "  ❌ Click tracking failed");

        // Test 4: Signup Attribution
        $this->info('👤 Test 4: Signup Attribution');
        $newUser = $this->createTestUser();
        $attribution = $referralService->attributeSignup($newUser, $request);
        if ($attribution) {
            $this->info("  ✓ Attribution created for user: {$newUser->nom_complet}");
        } else {
            $this->warn("  ⚠️ No attribution created (this might be expected)");
        }

        // Test 5: Verification Flow
        $this->info('✅ Test 5: Verification Flow');
        if ($attribution) {
            $referralService->markAttributionAsVerified($newUser);
            $attribution->refresh();
            $this->info($attribution->verified ? "  ✓ Attribution marked as verified" : "  ❌ Verification failed");
        } else {
            $this->warn("  ⚠️ Skipping verification test (no attribution)");
        }

        // Test 6: Statistics Generation
        $this->info('📊 Test 6: Statistics Generation');
        $stats = $referralService->getAffiliateStats($affiliate);
        $this->info("  ✓ Stats generated:");
        $this->info("    - Clicks: {$stats['clicks']}");
        $this->info("    - Signups: {$stats['signups']}");
        $this->info("    - Verified Signups: {$stats['verified_signups']}");
        $this->info("    - Conversion Rate: {$stats['conversion_rate']}%");
        $this->info("    - Total Points: {$stats['total_points']}");

        // Test 7: Dispensation Creation
        $this->info('🎁 Test 7: Dispensation Creation');
        $admin = $this->getOrCreateTestAdmin();
        $dispensation = $referralService->createDispensation(
            $affiliate,
            100,
            'Test dispensation for referral system validation',
            $admin,
            'TEST_REF_001'
        );
        $this->info("  ✓ Dispensation created with {$dispensation->points} points");

        // Test 8: Edge Cases
        $this->info('🧪 Test 8: Edge Cases');
        $this->testEdgeCases($referralService, $affiliate);

        // Test 9: Database Integrity
        $this->info('🔍 Test 9: Database Integrity');
        $this->validateDatabaseIntegrity();

        $this->info('');
        $this->info('🎉 Referral System Testing Complete!');
        $this->displaySummary();
    }

    private function resetTestData()
    {
        $this->warn('🗑️ Resetting test data...');
        
        // Delete test data (be careful in production!)
        ReferralDispensation::where('comment', 'LIKE', '%test%')->delete();
        ReferralAttribution::whereHas('newUser', function($q) {
            $q->where('email', 'LIKE', '%test%');
        })->delete();
        ReferralClick::where('user_agent', 'LIKE', '%TestAgent%')->delete();
        
        User::where('email', 'LIKE', '%test%')->delete();
        
        $this->info('  ✓ Test data reset');
    }

    private function getOrCreateTestAffiliate(): ProfilAffilie
    {
        $user = User::where('email', 'test-affiliate@example.com')->first();
        
        if (!$user) {
            $user = User::create([
                'nom_complet' => 'Test Affiliate',
                'email' => 'test-affiliate@example.com',
                'mot_de_passe_hash' => Hash::make('password'),
                'statut' => 'actif',
                'email_verifie' => true,
                'kyc_statut' => 'valide',
            ]);
            $user->assignRole('affiliate');
        }

        $affiliate = $user->profilAffilie;
        if (!$affiliate) {
            $affiliate = ProfilAffilie::create([
                'utilisateur_id' => $user->id,
                'gamme_id' => \App\Models\GammeAffilie::first()?->id,
                'points' => 0,
                'statut' => 'actif',
            ]);
        }

        return $affiliate;
    }

    private function getOrCreateTestAdmin(): User
    {
        $admin = User::where('email', 'test-admin@example.com')->first();
        
        if (!$admin) {
            $admin = User::create([
                'nom_complet' => 'Test Admin',
                'email' => 'test-admin@example.com',
                'mot_de_passe_hash' => Hash::make('password'),
                'statut' => 'actif',
                'email_verifie' => true,
                'kyc_statut' => 'non_requis',
            ]);
            $admin->assignRole('admin');
        }

        return $admin;
    }

    private function createTestUser(): User
    {
        return User::create([
            'nom_complet' => 'Test User ' . rand(1000, 9999),
            'email' => 'test-user-' . rand(1000, 9999) . '@example.com',
            'mot_de_passe_hash' => Hash::make('password'),
            'statut' => 'actif',
            'email_verifie' => true,
            'kyc_statut' => 'non_requis',
        ]);
    }

    private function createMockRequest(array $params = []): Request
    {
        $request = new Request($params);
        $request->server->set('HTTP_USER_AGENT', 'TestAgent/1.0');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->server->set('HTTP_REFERER', 'https://example.com');
        
        return $request;
    }

    private function testEdgeCases(ReferralService $referralService, ProfilAffilie $affiliate)
    {
        // Test invalid referral code
        $request = $this->createMockRequest(['ref' => 'INVALID']);
        $tracked = $referralService->trackClick('INVALID', $request);
        $this->info($tracked ? "  ❌ Invalid code should not track" : "  ✓ Invalid code correctly rejected");

        // Test self-referral prevention
        $selfRequest = $this->createMockRequest(['ref' => $referralService->getOrCreateReferralCode($affiliate)->code]);
        $attribution = $referralService->attributeSignup($affiliate->utilisateur, $selfRequest);
        $this->info($attribution ? "  ❌ Self-referral should be blocked" : "  ✓ Self-referral correctly blocked");

        // Test duplicate attribution
        $existingUser = User::where('email', 'LIKE', '%test%')->first();
        if ($existingUser) {
            $duplicateAttribution = $referralService->attributeSignup($existingUser, $selfRequest);
            $this->info($duplicateAttribution ? "  ⚠️ Duplicate attribution created" : "  ✓ Duplicate attribution prevented");
        }
    }

    private function validateDatabaseIntegrity()
    {
        $issues = [];

        // Check for orphaned records
        $orphanedClicks = ReferralClick::whereNotExists(function($query) {
            $query->select('*')
                  ->from('referral_codes')
                  ->whereColumn('referral_codes.code', 'referral_clicks.referral_code');
        })->count();

        if ($orphanedClicks > 0) {
            $issues[] = "Found {$orphanedClicks} orphaned clicks";
        }

        $orphanedAttributions = ReferralAttribution::whereNotExists(function($query) {
            $query->select('*')
                  ->from('users')
                  ->whereColumn('users.id', 'referral_attributions.new_user_id');
        })->count();

        if ($orphanedAttributions > 0) {
            $issues[] = "Found {$orphanedAttributions} orphaned attributions";
        }

        if (empty($issues)) {
            $this->info("  ✓ Database integrity check passed");
        } else {
            foreach ($issues as $issue) {
                $this->warn("  ⚠️ {$issue}");
            }
        }
    }

    private function displaySummary()
    {
        $totalCodes = ReferralCode::count();
        $totalClicks = ReferralClick::count();
        $totalAttributions = ReferralAttribution::count();
        $totalDispensations = ReferralDispensation::count();
        $verifiedAttributions = ReferralAttribution::where('verified', true)->count();

        $this->info('📈 System Summary:');
        $this->info("  - Referral Codes: {$totalCodes}");
        $this->info("  - Total Clicks: {$totalClicks}");
        $this->info("  - Total Attributions: {$totalAttributions}");
        $this->info("  - Verified Attributions: {$verifiedAttributions}");
        $this->info("  - Total Dispensations: {$totalDispensations}");
        
        if ($totalClicks > 0 && $totalAttributions > 0) {
            $conversionRate = round(($totalAttributions / $totalClicks) * 100, 2);
            $this->info("  - Overall Conversion Rate: {$conversionRate}%");
        }
    }
}
