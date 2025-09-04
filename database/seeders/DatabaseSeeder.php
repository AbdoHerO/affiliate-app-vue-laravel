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
        $this->command->info('🚀 Starting PRODUCTION-READY database seeding...');

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

        // 2. CATALOG: Essential catalog structure (Variants/Attributes + Production Categories/Boutique)
        $this->command->info('🏪 Step 2: Essential Catalog (Variants, Categories, Boutique)');
        $this->call([
            VariantCatalogSeeder::class,
            CategorieSeeder::class,  // Traditional Moroccan clothing categories
            BoutiqueSeeder::class,   // Tujjar Store only
        ]);

        // COMMENTED OUT FOR PRODUCTION - Dynamic product data
        // $this->command->info('🏪 Step 2: Catalog (Products)');
        // $this->call([
        //     ComprehensiveProductSeeder::class,
        // ]);

        // 3. INTEGRATIONS: External services (Essential cities only)
        $this->command->info('🌐 Step 3: Integrations (OzonExpress Cities)');
        $this->call([
            OzonExpressCitiesSeeder::class,
        ]);

        // COMMENTED OUT FOR PRODUCTION - Test credentials
        // $this->call([
        //     OzonExpressSeeder::class,
        // ]);

        // 4. USERS: Essential users only (1 admin + 1 affiliate)
        $this->command->info('👥 Step 4: Essential Users (Admin + Affiliate)');
        $this->call([
            AffiliateSeeder::class,  // Will be modified to create minimal users
        ]);

        // COMMENTED OUT FOR PRODUCTION - Dynamic order data
        // $this->command->info('📦 Step 5: Orders (Pre-orders & Shipping)');
        // $this->call([
        //     OrdersSeeder::class,         // Basic orders with client final integration
        //     OrdersE2ETestSeeder::class,  // Comprehensive order scenarios
        // ]);

        // COMMENTED OUT FOR PRODUCTION - Commission test data
        // $this->command->info('💰 Step 6: Commissions');
        // $this->call([
        //     CommissionTestSeeder::class,
        // ]);

        // COMMENTED OUT FOR PRODUCTION - Payment/withdrawal test data
        // $this->command->info('🏦 Step 7: Payments & Withdrawals');
        // $this->call([
        //     ComprehensiveWithdrawalSeeder::class,  // Comprehensive withdrawal data
        //     FinalWithdrawalSeeder::class,          // Final 2 withdrawals to reach 10 total
        // ]);

        // COMMENTED OUT FOR PRODUCTION - Support ticket test data
        // $this->command->info('🎧 Step 8: Support System');
        // $this->call([
        //     TicketSeeder::class,
        // ]);

        // COMMENTED OUT FOR PRODUCTION - Dashboard analytics test data
        // $this->command->info('📊 Step 9: Dashboard Analytics Data');
        // $this->call([
        //     SimpleDashboardSeeder::class,
        // ]);

        // COMMENTED OUT FOR PRODUCTION - Referral system test data
        // $this->command->info('🔗 Step 10: Referral System');
        // $this->call([
        //     ReferralSystemSeeder::class,
        //     CODReferralSeeder::class,  // Specific data for your test users
        //     PointsSystemSeeder::class,  // New points dispensation system
        // ]);

        $this->command->info('');
        $this->command->info('✅ PRODUCTION-READY database seeding finished!');
        $this->command->info('🎯 Clean minimal database ready for production testing.');
        $this->command->info('📝 Only essential data: Roles, Settings, Variants, Cities, and 2 test users.');
    }
}
