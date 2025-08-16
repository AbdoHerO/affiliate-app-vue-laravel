<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OzonExpressSeeder extends Seeder
{
    /**
     * Run the database seeds for OzonExpress integration.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting OzonExpress integration seeding...');
        $this->command->newLine();

        // Seed app settings first
        $this->command->info('📋 Seeding app settings...');
        $this->call(AppSettingsSeeder::class);
        $this->command->newLine();

        // Then seed cities
        $this->command->info('🏙️  Seeding OzonExpress cities...');
        $this->call(OzonExpressCitiesSeeder::class);
        $this->command->newLine();

        $this->command->info('✅ OzonExpress integration seeding completed!');
        $this->command->newLine();
        
        $this->command->info('Next steps:');
        $this->command->info('1. Configure your OzonExpress credentials in the admin panel');
        $this->command->info('2. Visit /admin/integrations/ozon/credentials to set up API keys');
        $this->command->info('3. Visit /admin/integrations/ozon/cities to manage cities');
    }
}
