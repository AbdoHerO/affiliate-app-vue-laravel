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

        // Check environment safety
        if (app()->environment('production')) {
            $this->command->error('❌ Cannot run seeders in production environment!');
            return;
        }

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
            // VariantCatalogSeeder::class, // Temporarily disabled due to UUID migration issues
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
            OrdersE2ETestSeeder::class,  // Comprehensive order scenarios
        ]);

        // 6. COMMISSIONS & PAYMENTS: Skip for now due to data dependencies
        $this->command->info('💰 Step 6: Commissions & Payments (Manual setup required)');
        $this->command->warn('⚠️  Commission and withdrawal seeders require manual setup.');
        $this->command->info('   Run these commands separately after initial setup:');
        $this->command->info('   • php artisan db:seed --class=CommissionTestSeeder');
        $this->command->info('   • php artisan db:seed --class=WithdrawalDemoSeeder');

        $this->command->info('');
        $this->command->info('✅ Complete database seeding finished!');
        $this->command->info('🎯 Your system is ready for testing with comprehensive data.');
    }
}
