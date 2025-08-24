<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Commande;
use App\Models\CommissionAffilie;
use App\Models\ReferralCode;
use App\Models\ReferralClick;
use App\Models\ReferralAttribution;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Faker\Factory as Faker;

class SimpleDashboardSeeder extends Seeder
{
    private $faker;

    public function __construct()
    {
        $this->faker = Faker::create('fr_FR');
    }

    public function run(): void
    {
        $this->command->info('🚀 Creating dashboard data for existing users...');

        // 1. Create commissions for existing orders
        if (DB::table('commissions_affilies')->count() == 0) {
            $this->createCommissions();
        } else {
            $this->command->info('💰 Commissions already exist, skipping...');
        }

        // 2. Create referral codes for existing affiliates
        if (DB::table('referral_codes')->count() == 0) {
            $this->createReferralCodes();
        } else {
            $this->command->info('🔗 Referral codes already exist, skipping...');
        }

        // 3. Create referral clicks
        if (DB::table('referral_clicks')->count() == 0) {
            $this->createReferralClicks();
        } else {
            $this->command->info('👆 Referral clicks already exist, skipping...');
        }

        // 4. Create withdrawals
        if (DB::table('withdrawals')->count() == 0) {
            $this->createWithdrawals();
        } else {
            $this->command->info('💸 Withdrawals already exist, skipping...');
        }

        // 5. Create referral attributions
        if (DB::table('referral_attributions')->count() == 0) {
            $this->createReferralAttributions();
        } else {
            $this->command->info('👥 Referral attributions already exist, skipping...');
        }

        $this->command->info('✅ Dashboard data created successfully!');
    }

    private function createCommissions(): void
    {
        $this->command->info('💰 Creating commissions for existing orders...');

        $orders = Commande::with('affiliate.profilAffilie')->get();
        $commissions = [];

        foreach ($orders as $order) {
            $affiliateProfile = $order->affiliate->profilAffilie ?? null;

            if (!$affiliateProfile) {
                continue; // Skip if user doesn't have affiliate profile
            }

            $rate = $this->faker->randomFloat(4, 0.05, 0.15); // 5-15% commission
            $amount = $order->total_ttc * $rate;

            $commissions[] = [
                'id' => $this->faker->uuid,
                'user_id' => $order->user_id,
                'commande_id' => $order->id,
                'commande_article_id' => null, // Order-level commission
                'affilie_id' => $affiliateProfile->id,
                'type' => 'vente',
                'base_amount' => $order->total_ttc,
                'rate' => $rate,
                'qty' => 1,
                'amount' => $amount,
                'montant' => $amount,
                'currency' => 'MAD',
                'status' => $this->faker->randomElement(['calculated', 'eligible', 'approved', 'paid']),
                'statut' => $this->faker->randomElement(['en_attente', 'valide', 'paye']),
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        }

        // Batch insert
        foreach (array_chunk($commissions, 50) as $chunk) {
            DB::table('commissions_affilies')->insert($chunk);
        }

        $this->command->info("✅ Created " . count($commissions) . " commissions");
    }

    private function createReferralCodes(): void
    {
        $this->command->info('🔗 Creating referral codes...');

        $affiliates = DB::table('profils_affilies')->get();
        $codes = [];
        $counter = 1;

        foreach ($affiliates as $affiliate) {
            $codes[] = [
                'id' => $this->faker->uuid,
                'affiliate_id' => $affiliate->id,
                'code' => 'REF' . str_pad($counter, 6, '0', STR_PAD_LEFT),
                'active' => true,
                'created_at' => $affiliate->created_at,
                'updated_at' => $affiliate->updated_at,
            ];
            $counter++;
        }

        DB::table('referral_codes')->insert($codes);
        $this->command->info("✅ Created " . count($codes) . " referral codes");
    }

    private function createReferralClicks(): void
    {
        $this->command->info('👆 Creating referral clicks...');

        $codes = DB::table('referral_codes')->get();
        $clicks = [];

        foreach ($codes as $code) {
            $clickCount = $this->faker->numberBetween(10, 100);
            
            for ($i = 0; $i < $clickCount; $i++) {
                $clickDate = $this->faker->dateTimeBetween('2024-01-01', '2024-08-23');
                
                $clicks[] = [
                    'id' => $this->faker->uuid,
                    'referral_code' => $code->code,
                    'ip_hash' => hash('sha256', $this->faker->ipv4),
                    'user_agent' => $this->faker->userAgent,
                    'referer_url' => $this->faker->optional()->url,
                    'clicked_at' => $clickDate,
                    'created_at' => $clickDate,
                    'updated_at' => $clickDate,
                ];
            }
        }

        // Batch insert
        foreach (array_chunk($clicks, 100) as $chunk) {
            DB::table('referral_clicks')->insert($chunk);
        }

        $this->command->info("✅ Created " . count($clicks) . " referral clicks");
    }

    private function createWithdrawals(): void
    {
        $this->command->info('💸 Creating withdrawal requests...');

        $affiliates = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.role_id', 2)
            ->select('users.*')
            ->get();

        $withdrawals = [];

        foreach ($affiliates as $affiliate) {
            if ($this->faker->boolean(40)) { // 40% chance
                $withdrawals[] = [
                    'id' => $this->faker->uuid,
                    'user_id' => $affiliate->id,
                    'amount' => $this->faker->randomFloat(2, 100, 1000),
                    'method' => 'bank_transfer',
                    'status' => $this->faker->randomElement(['pending', 'approved', 'paid', 'rejected']),
                    'iban_rib' => $this->faker->iban('MA'),
                    'bank_type' => $this->faker->randomElement(['Attijariwafa Bank', 'BMCE Bank', 'Banque Populaire']),
                    'notes' => $this->faker->optional()->sentence,
                    'created_at' => $this->faker->dateTimeBetween('2024-01-01', '2024-08-23'),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($withdrawals)) {
            DB::table('withdrawals')->insert($withdrawals);
        }

        $this->command->info("✅ Created " . count($withdrawals) . " withdrawal requests");
    }

    private function createReferralAttributions(): void
    {
        $this->command->info('👥 Creating referral attributions...');

        $affiliates = DB::table('profils_affilies')->get();
        $users = DB::table('users')->pluck('id')->toArray();
        $referralCodes = DB::table('referral_codes')->pluck('code', 'affiliate_id')->toArray();
        $attributions = [];

        foreach ($affiliates as $affiliate) {
            $signupCount = $this->faker->numberBetween(0, 5); // Reduced from 10 to 5
            $referralCode = $referralCodes[$affiliate->id] ?? null;

            if (!$referralCode) {
                continue; // Skip if no referral code exists
            }

            for ($i = 0; $i < $signupCount; $i++) {
                $attributedAt = $this->faker->dateTimeBetween('2024-01-01', '2024-08-23');
                $verified = $this->faker->boolean(70);

                $attributions[] = [
                    'id' => $this->faker->uuid,
                    'referrer_affiliate_id' => $affiliate->id,
                    'new_user_id' => $this->faker->randomElement($users),
                    'referral_code' => $referralCode,
                    'attributed_at' => $attributedAt,
                    'verified' => $verified,
                    'verified_at' => $verified ? $this->faker->dateTimeBetween($attributedAt, '2024-08-23') : null,
                    'source' => $this->faker->randomElement(['web', 'mobile']),
                    'ip_hash' => hash('sha256', $this->faker->ipv4),
                    'created_at' => $attributedAt,
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($attributions)) {
            foreach (array_chunk($attributions, 50) as $chunk) {
                DB::table('referral_attributions')->insert($chunk);
            }
        }

        $this->command->info("✅ Created " . count($attributions) . " referral attributions");
    }
}
