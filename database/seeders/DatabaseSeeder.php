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
        // Seed roles, permissions, and default users
        $this->call([
            RolePermissionSeeder::class,
            BoutiqueSeeder::class,
            CategorieSeeder::class,
            ComprehensiveProductSeeder::class,

            // OzonExpress integration seeders
            AppSettingsSeeder::class,
            OzonExpressCitiesSeeder::class,
        ]);
    }
}
