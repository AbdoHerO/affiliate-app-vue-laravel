<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProduitVariante;
use App\Models\Stock;
use App\Models\Entrepot;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏭 Seeding stock data...');

        // Ensure we have at least one warehouse
        $warehouse = Entrepot::first();
        if (!$warehouse) {
            $warehouse = Entrepot::create([
                'nom' => 'Entrepôt Principal',
                'adresse' => 'Adresse par défaut',
                'actif' => true
            ]);
            $this->command->info('✅ Created default warehouse');
        }

        // Get all variants that don't have stock records
        $variantsWithoutStock = ProduitVariante::whereDoesntHave('stocks')->get();
        
        $this->command->info("📦 Found {$variantsWithoutStock->count()} variants without stock");

        $created = 0;
        foreach ($variantsWithoutStock as $variant) {
            // Create stock record with random quantity between 5-50
            Stock::create([
                'variante_id' => $variant->id,
                'entrepot_id' => $warehouse->id,
                'qte_disponible' => rand(5, 50),
                'qte_reservee' => 0
            ]);
            $created++;
        }

        $this->command->info("✅ Created {$created} stock records");

        // Update existing stock records that have 0 quantity
        $zeroStockCount = Stock::where('qte_disponible', 0)->count();
        if ($zeroStockCount > 0) {
            Stock::where('qte_disponible', 0)->update([
                'qte_disponible' => rand(5, 20)
            ]);
            $this->command->info("✅ Updated {$zeroStockCount} zero-stock records");
        }

        $this->command->info('🎯 Stock seeding completed!');
    }
}
