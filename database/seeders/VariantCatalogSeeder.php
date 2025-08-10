<?php

namespace Database\Seeders;

use App\Models\VariantAttribut;
use App\Models\VariantValeur;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VariantCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Size attribute
        $sizeAttribut = VariantAttribut::create([
            'code' => 'size',
            'nom' => 'Size',
            'actif' => true
        ]);

        // Create size values
        $sizeValues = [
            ['code' => 'xs', 'libelle' => 'Extra Small', 'ordre' => 1],
            ['code' => 's', 'libelle' => 'Small', 'ordre' => 2],
            ['code' => 'm', 'libelle' => 'Medium', 'ordre' => 3],
            ['code' => 'l', 'libelle' => 'Large', 'ordre' => 4],
            ['code' => 'xl', 'libelle' => 'Extra Large', 'ordre' => 5],
            ['code' => 'xxl', 'libelle' => '2X Large', 'ordre' => 6],
        ];

        foreach ($sizeValues as $value) {
            VariantValeur::create([
                'attribut_id' => $sizeAttribut->id,
                'code' => $value['code'],
                'libelle' => $value['libelle'],
                'ordre' => $value['ordre'],
                'actif' => true
            ]);
        }

        // Create Color attribute
        $colorAttribut = VariantAttribut::create([
            'code' => 'color',
            'nom' => 'Color',
            'actif' => true
        ]);

        // Create color values
        $colorValues = [
            ['code' => 'black', 'libelle' => 'Black', 'ordre' => 1],
            ['code' => 'white', 'libelle' => 'White', 'ordre' => 2],
            ['code' => 'red', 'libelle' => 'Red', 'ordre' => 3],
            ['code' => 'blue', 'libelle' => 'Blue', 'ordre' => 4],
            ['code' => 'green', 'libelle' => 'Green', 'ordre' => 5],
            ['code' => 'yellow', 'libelle' => 'Yellow', 'ordre' => 6],
            ['code' => 'gray', 'libelle' => 'Gray', 'ordre' => 7],
            ['code' => 'navy', 'libelle' => 'Navy', 'ordre' => 8],
        ];

        foreach ($colorValues as $value) {
            VariantValeur::create([
                'attribut_id' => $colorAttribut->id,
                'code' => $value['code'],
                'libelle' => $value['libelle'],
                'ordre' => $value['ordre'],
                'actif' => true
            ]);
        }

        // Create Material attribute
        $materialAttribut = VariantAttribut::create([
            'code' => 'material',
            'nom' => 'Material',
            'actif' => true
        ]);

        // Create material values
        $materialValues = [
            ['code' => 'cotton', 'libelle' => 'Cotton', 'ordre' => 1],
            ['code' => 'polyester', 'libelle' => 'Polyester', 'ordre' => 2],
            ['code' => 'wool', 'libelle' => 'Wool', 'ordre' => 3],
            ['code' => 'silk', 'libelle' => 'Silk', 'ordre' => 4],
            ['code' => 'leather', 'libelle' => 'Leather', 'ordre' => 5],
            ['code' => 'denim', 'libelle' => 'Denim', 'ordre' => 6],
        ];

        foreach ($materialValues as $value) {
            VariantValeur::create([
                'attribut_id' => $materialAttribut->id,
                'code' => $value['code'],
                'libelle' => $value['libelle'],
                'ordre' => $value['ordre'],
                'actif' => true
            ]);
        }

        $this->command->info('Variant catalog seeded successfully!');
    }
}
