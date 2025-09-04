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
        // Get or create Size attribute
        $sizeAttribut = VariantAttribut::firstOrCreate(
            ['code' => 'size'],
            ['nom' => 'Size', 'actif' => true]
        );

        // Create size values if they don't exist
        $sizeValues = [
            ['code' => 'xs', 'libelle' => 'Extra Small', 'ordre' => 1],
            ['code' => 's', 'libelle' => 'Small', 'ordre' => 2],
            ['code' => 'm', 'libelle' => 'Medium', 'ordre' => 3],
            ['code' => 'l', 'libelle' => 'Large', 'ordre' => 4],
            ['code' => 'xl', 'libelle' => 'Extra Large', 'ordre' => 5],
            ['code' => 'xxl', 'libelle' => '2X Large', 'ordre' => 6],
        ];

        foreach ($sizeValues as $value) {
            VariantValeur::firstOrCreate(
                [
                    'attribut_id' => $sizeAttribut->id,
                    'code' => $value['code']
                ],
                [
                    'libelle' => $value['libelle'],
                    'ordre' => $value['ordre'],
                    'actif' => true
                ]
            );
        }

        // Get or create Color attribute
        $colorAttribut = VariantAttribut::firstOrCreate(
            ['code' => 'color'],
            ['nom' => 'Color', 'actif' => true]
        );

        // Create color values with hex colors
        $colorValues = [
            ['code' => 'black', 'libelle' => 'Noir', 'ordre' => 1, 'hex_color' => '#000000'],
            ['code' => 'white', 'libelle' => 'Blanc', 'ordre' => 2, 'hex_color' => '#FFFFFF'],
            ['code' => 'red', 'libelle' => 'Rouge', 'ordre' => 3, 'hex_color' => '#DC2626'],
            ['code' => 'blue', 'libelle' => 'Bleu', 'ordre' => 4, 'hex_color' => '#2563EB'],
            ['code' => 'green', 'libelle' => 'Vert', 'ordre' => 5, 'hex_color' => '#16A34A'],
            ['code' => 'yellow', 'libelle' => 'Jaune', 'ordre' => 6, 'hex_color' => '#EAB308'],
            ['code' => 'gray', 'libelle' => 'Gris', 'ordre' => 7, 'hex_color' => '#6B7280'],
            ['code' => 'navy', 'libelle' => 'Bleu Marine', 'ordre' => 8, 'hex_color' => '#1E3A8A'],
            ['code' => 'pink', 'libelle' => 'Rose', 'ordre' => 9, 'hex_color' => '#EC4899'],
            ['code' => 'purple', 'libelle' => 'Violet', 'ordre' => 10, 'hex_color' => '#7C3AED'],
            ['code' => 'orange', 'libelle' => 'Orange', 'ordre' => 11, 'hex_color' => '#EA580C'],
            ['code' => 'brown', 'libelle' => 'Marron', 'ordre' => 12, 'hex_color' => '#92400E'],
        ];

        foreach ($colorValues as $value) {
            VariantValeur::updateOrCreate(
                [
                    'attribut_id' => $colorAttribut->id,
                    'code' => $value['code']
                ],
                [
                    'libelle' => $value['libelle'],
                    'ordre' => $value['ordre'],
                    'hex_color' => $value['hex_color'],
                    'actif' => true
                ]
            );
        }

        // Get or create Material attribute
        $materialAttribut = VariantAttribut::firstOrCreate(
            ['code' => 'material'],
            ['nom' => 'Material', 'actif' => true]
        );

        // Create material values if they don't exist
        $materialValues = [
            ['code' => 'cotton', 'libelle' => 'Cotton', 'ordre' => 1],
            ['code' => 'polyester', 'libelle' => 'Polyester', 'ordre' => 2],
            ['code' => 'wool', 'libelle' => 'Wool', 'ordre' => 3],
            ['code' => 'silk', 'libelle' => 'Silk', 'ordre' => 4],
            ['code' => 'leather', 'libelle' => 'Leather', 'ordre' => 5],
            ['code' => 'denim', 'libelle' => 'Denim', 'ordre' => 6],
        ];

        foreach ($materialValues as $value) {
            VariantValeur::firstOrCreate(
                [
                    'attribut_id' => $materialAttribut->id,
                    'code' => $value['code']
                ],
                [
                    'libelle' => $value['libelle'],
                    'ordre' => $value['ordre'],
                    'actif' => true
                ]
            );
        }

        $this->command->info('Variant catalog seeded successfully!');
    }
}
