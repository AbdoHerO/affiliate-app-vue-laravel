<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting complete database seeding...');

        // // Check environment safety
        // if (app()->environment('production')) {
        //     $this->command->error('❌ Cannot run seeders in production environment!');
        //     return;
        // }

        // 1. FOUNDATION: Roles, permissions, and basic settings
        $this->command->info('📋 Step 1: Foundation (Roles, Permissions, Settings)');
        $this->call([
            RolePermissionSeeder::class,
            AppSettingsSeeder::class,
            WithdrawalSettingsSeeder::class,
            CommissionSettingsSeeder::class,
        ]);

        // 2. CATALOG: Categories, boutiques, and products
        $this->command->info('🏪 Step 2: Catalog (Categories, Boutiques, Products)');
        $this->call([
            BoutiqueSeeder::class,
            CategorieSeeder::class,
            VariantCatalogSeeder::class,
            ComprehensiveProductSeeder::class,
        ]);

        // 3. INTEGRATIONS: External services
        $this->command->info('🌐 Step 3: Integrations (OzonExpress)');
        $this->call([
            OzonExpressCitiesSeeder::class,
            OzonExpressSeeder::class,
        ]);

        // 4. USERS: Affiliates and test users
        $this->command->info('👥 Step 4: Users & Affiliates');
        $this->call([
            AffiliateSeeder::class,
        ]);

        // 5. ORDERS: Pre-orders and shipping orders
        $this->command->info('📦 Step 5: Orders (Pre-orders & Shipping)');
        $this->call([
            OrdersSeeder::class,         // Basic orders with client final integration
            OrdersE2ETestSeeder::class,  // Comprehensive order scenarios
        ]);

        // 6. COMMISSIONS: Commission calculations
        $this->command->info('💰 Step 6: Commissions');
        $this->call([
            CommissionTestSeeder::class,
        ]);

        // 7. PAYMENTS & WITHDRAWALS: Complete payment system
        $this->command->info('🏦 Step 7: Payments & Withdrawals');
        $this->call([
            ComprehensiveWithdrawalSeeder::class,  // Comprehensive withdrawal data
            FinalWithdrawalSeeder::class,          // Final 2 withdrawals to reach 10 total
        ]);

        // 8. SUPPORT: Support tickets and communications
        $this->command->info('🎧 Step 8: Support System');
        $this->call([
            TicketSeeder::class,
        ]);

        // 9. REFERRAL SYSTEM: Referral codes, clicks, attributions, and dispensations
        // COMMENTED OUT FOR CLEAN TESTING - Uncomment when you want test data
        // $this->command->info('🔗 Step 9: Referral System');
        // $this->call([
        //     ReferralSystemSeeder::class,
        //     CODReferralSeeder::class,  // Specific data for your test users
        //     PointsSystemSeeder::class,  // New points dispensation system
        // ]);

        $this->command->info('');
        $this->command->info('✅ Complete database seeding finished!');
        $this->command->info('🎯 Your system is ready for testing with comprehensive data.');
    }
}
