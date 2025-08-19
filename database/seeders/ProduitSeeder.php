<?php

namespace Database\Seeders;

use App\Models\Produit;
use App\Models\ProduitImage;
use App\Models\ProduitVariante;
use App\Models\Stock;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\VariantAttribut;
use App\Models\VariantValeur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProduitSeeder extends Seeder
{
    public function run(): void
    {
        // Get some boutiques and categories for products
        $boutiques = Boutique::take(3)->get();
        $categories = Categorie::take(5)->get();

        if ($boutiques->isEmpty() || $categories->isEmpty()) {
            $this->command->warn('Please run BoutiqueSeeder and CategorySeeder first');
            return;
        }

        // Get or create an entrepot
        $entrepot = \App\Models\Entrepot::first();
        if (!$entrepot) {
            $entrepot = \App\Models\Entrepot::create([
                'boutique_id' => $boutiques->first()->id,
                'nom' => 'Entrepôt Principal',
                'adresse' => 'Casablanca, Maroc',
                'actif' => true
            ]);
        }

        // Create variant attributes if they don't exist
        $this->createVariantAttributes();

        $this->createProductsWithVariants($boutiques, $categories, $entrepot);

        $this->command->info('Produit seeder completed successfully!');
    }

    private function createVariantAttributes(): void
    {
        // Create size attribute if it doesn't exist
        $sizeAttr = VariantAttribut::firstOrCreate([
            'code' => 'taille',
            'nom' => 'Taille',
            'actif' => true
        ]);

        // Create color attribute if it doesn't exist
        $colorAttr = VariantAttribut::firstOrCreate([
            'code' => 'couleur',
            'nom' => 'Couleur',
            'actif' => true
        ]);

        // Create size values
        $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
        foreach ($sizes as $index => $size) {
            VariantValeur::firstOrCreate([
                'attribut_id' => $sizeAttr->id,
                'code' => strtolower($size),
                'libelle' => $size,
                'actif' => true,
                'ordre' => $index + 1
            ]);
        }

        // Create color values
        $colors = [
            ['name' => 'Rouge', 'hex' => '#FF0000'],
            ['name' => 'Bleu', 'hex' => '#0000FF'],
            ['name' => 'Vert', 'hex' => '#00FF00'],
            ['name' => 'Noir', 'hex' => '#000000'],
            ['name' => 'Blanc', 'hex' => '#FFFFFF'],
            ['name' => 'Gris', 'hex' => '#808080'],
            ['name' => 'Rose', 'hex' => '#FFC0CB'],
            ['name' => 'Jaune', 'hex' => '#FFFF00'],
            ['name' => 'Orange', 'hex' => '#FFA500'],
            ['name' => 'Violet', 'hex' => '#800080'],
            ['name' => 'Beige', 'hex' => '#F5F5DC'],
            ['name' => 'Marron', 'hex' => '#8B4513'],
        ];

        foreach ($colors as $index => $color) {
            VariantValeur::firstOrCreate([
                'attribut_id' => $colorAttr->id,
                'code' => strtolower($color['name']),
                'libelle' => $color['name'],
                'actif' => true,
                'ordre' => $index + 1
            ]);
        }
    }

    private function createProductsWithVariants($boutiques, $categories, $entrepot): void
    {
        $products = [
            [
                'titre' => 'Robe Élégante Brodée',
                'description' => 'Robe élégante avec broderies délicates, parfaite pour les occasions spéciales. Tissu fluide et confortable.',
                'prix_achat' => 220.00,
                'prix_vente' => 380.00,
                'prix_affilie' => 90.00,
                'actif' => true,
                'quantite_min' => 2,
                'notes_admin' => 'Produit premium, forte demande',
                'rating_value' => 4.8,
                'images' => [
                    'https://images.unsplash.com/photo-1566479179817-c0b5b4b8b1cc?w=500&h=600&fit=crop',
                    'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=500&h=600&fit=crop',
                ],
                'variants' => [
                    ['type' => 'taille', 'values' => ['S', 'M', 'L', 'XL'], 'stock_each' => [25, 35, 30, 15]],
                    ['type' => 'couleur', 'values' => ['Beige', 'Rose', 'Bleu'], 'stock_each' => [40, 35, 30], 'images' => [
                        'Beige' => 'https://images.unsplash.com/photo-1566479179817-c0b5b4b8b1cc?w=500&h=600&fit=crop',
                        'Rose' => 'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=500&h=600&fit=crop',
                        'Bleu' => 'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?w=500&h=600&fit=crop',
                    ]],
                ]
            ],
            [
                'titre' => 'Sneakers Tendance Urbain',
                'description' => 'Baskets urbaines tendance avec design moderne. Confort optimal pour un style décontracté.',
                'prix_achat' => 180.00,
                'prix_vente' => 320.00,
                'prix_affilie' => 75.00,
                'actif' => true,
                'quantite_min' => 2,
                'notes_admin' => 'Chaussures très populaires',
                'rating_value' => 4.6,
                'images' => [
                    'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=500&h=500&fit=crop',
                    'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=500&h=500&fit=crop',
                ],
                'variants' => [
                    ['type' => 'taille', 'values' => ['39', '40', '41', '42', '43'], 'stock_each' => [12, 18, 22, 15, 8]],
                    ['type' => 'couleur', 'values' => ['Noir', 'Blanc', 'Rouge'], 'stock_each' => [30, 25, 20], 'images' => [
                        'Noir' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=500&h=500&fit=crop',
                        'Blanc' => 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=500&h=500&fit=crop',
                        'Rouge' => 'https://images.unsplash.com/photo-1551107696-a4b0c5a0d9a2?w=500&h=500&fit=crop',
                    ]],
                ]
            ],
            [
                'titre' => 'Chemise Casual Homme',
                'description' => 'Chemise décontractée pour homme, coupe moderne et tissu respirant. Idéale pour le bureau ou les sorties.',
                'prix_achat' => 120.00,
                'prix_vente' => 220.00,
                'prix_affilie' => 55.00,
                'actif' => true,
                'quantite_min' => 3,
                'notes_admin' => 'Basique indispensable',
                'rating_value' => 4.4,
                'images' => [
                    'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=500&h=600&fit=crop',
                    'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?w=500&h=600&fit=crop',
                ],
                'variants' => [
                    ['type' => 'taille', 'values' => ['S', 'M', 'L', 'XL', 'XXL'], 'stock_each' => [15, 25, 30, 20, 10]],
                    ['type' => 'couleur', 'values' => ['Blanc', 'Bleu', 'Noir'], 'stock_each' => [35, 30, 25], 'images' => [
                        'Blanc' => 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=500&h=600&fit=crop',
                        'Bleu' => 'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?w=500&h=600&fit=crop',
                        'Noir' => 'https://images.unsplash.com/photo-1603252109303-2751441dd157?w=500&h=600&fit=crop',
                    ]],
                ]
            ],
            [
                'titre' => 'Sac à Main Élégant',
                'description' => 'Sac à main élégant en cuir synthétique de qualité. Design moderne avec plusieurs compartiments.',
                'prix_achat' => 150.00,
                'prix_vente' => 280.00,
                'prix_affilie' => 70.00,
                'actif' => true,
                'quantite_min' => 2,
                'notes_admin' => 'Accessoire tendance',
                'rating_value' => 4.7,
                'images' => [
                    'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=500&h=500&fit=crop',
                    'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=500&h=500&fit=crop',
                ],
                'variants' => [
                    ['type' => 'couleur', 'values' => ['Noir', 'Beige', 'Rouge'], 'stock_each' => [25, 20, 15], 'images' => [
                        'Noir' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=500&h=500&fit=crop',
                        'Beige' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=500&h=500&fit=crop',
                        'Rouge' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=500&h=500&fit=crop',
                    ]],
                ]
            ],
            [
                'titre' => 'Montre Connectée Sport',
                'description' => 'Montre connectée avec suivi fitness avancé, GPS intégré et résistance à l\'eau.',
                'prix_achat' => 250.00,
                'prix_vente' => 450.00,
                'prix_affilie' => 120.00,
                'actif' => true,
                'quantite_min' => 2,
                'notes_admin' => 'Technologie populaire',
                'rating_value' => 4.5,
                'images' => [
                    'https://images.unsplash.com/photo-1579586337278-3f436f25d4d4?w=500&h=500&fit=crop',
                    'https://images.unsplash.com/photo-1544117519-31a4b719223d?w=500&h=500&fit=crop',
                ],
                'variants' => [
                    ['type' => 'couleur', 'values' => ['Noir', 'Blanc', 'Rose'], 'stock_each' => [30, 25, 15], 'images' => [
                        'Noir' => 'https://images.unsplash.com/photo-1579586337278-3f436f25d4d4?w=500&h=500&fit=crop',
                        'Blanc' => 'https://images.unsplash.com/photo-1544117519-31a4b719223d?w=500&h=500&fit=crop',
                        'Rose' => 'https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=500&h=500&fit=crop',
                    ]],
                ]
            ],
            [
                'titre' => 'Veste Denim Vintage',
                'description' => 'Veste en denim style vintage, coupe décontractée. Parfaite pour un look casual chic.',
                'prix_achat' => 180.00,
                'prix_vente' => 320.00,
                'prix_affilie' => 80.00,
                'actif' => true,
                'quantite_min' => 2,
                'notes_admin' => 'Style intemporel',
                'rating_value' => 4.3,
                'images' => [
                    'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=500&h=600&fit=crop',
                    'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?w=500&h=600&fit=crop',
                ],
                'variants' => [
                    ['type' => 'taille', 'values' => ['S', 'M', 'L', 'XL'], 'stock_each' => [18, 25, 22, 15]],
                    ['type' => 'couleur', 'values' => ['Bleu', 'Noir', 'Blanc'], 'stock_each' => [35, 25, 20], 'images' => [
                        'Bleu' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=500&h=600&fit=crop',
                        'Noir' => 'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?w=500&h=600&fit=crop',
                        'Blanc' => 'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=500&h=600&fit=crop',
                    ]],
                ]
            ],
            [
                'titre' => 'Pantalon Chino Élégant',
                'description' => 'Pantalon chino élégant, coupe slim. Idéal pour un style business casual.',
                'prix_achat' => 90.00,
                'prix_vente' => 180.00,
                'prix_affilie' => 45.00,
                'actif' => true,
                'quantite_min' => 3,
                'notes_admin' => 'Basique homme',
                'rating_value' => 4.2,
                'images' => [
                    'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?w=500&h=600&fit=crop',
                    'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?w=500&h=600&fit=crop',
                ],
                'variants' => [
                    ['type' => 'taille', 'values' => ['30', '32', '34', '36', '38'], 'stock_each' => [12, 20, 25, 18, 10]],
                    ['type' => 'couleur', 'values' => ['Beige', 'Noir', 'Bleu'], 'stock_each' => [30, 25, 20], 'images' => [
                        'Beige' => 'https://images.unsplash.com/photo-1473966968600-fa801b869a1a?w=500&h=600&fit=crop',
                        'Noir' => 'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?w=500&h=600&fit=crop',
                        'Bleu' => 'https://images.unsplash.com/photo-1603252109303-2751441dd157?w=500&h=600&fit=crop',
                    ]],
                ]
            ],
        ];

        foreach ($products as $productData) {
            $boutique = $boutiques->random();
            $category = $categories->random();

            $images = $productData['images'];
            $variants = $productData['variants'] ?? [];
            unset($productData['images'], $productData['variants']);

            // Create product
            $product = Produit::create([
                'boutique_id' => $boutique->id,
                'categorie_id' => $category->id,
                'slug' => Str::slug($productData['titre']) . '-' . time() . '-' . rand(100, 999),
                ...$productData
            ]);

            // Add main images
            foreach ($images as $imageIndex => $imageUrl) {
                ProduitImage::create([
                    'produit_id' => $product->id,
                    'url' => $imageUrl,
                    'ordre' => $imageIndex + 1,
                ]);
            }

            // Create variants and stock
            $this->createVariantsForProduct($product, $variants, $entrepot);

            $this->command->info("Created product: {$product->titre}");
        }
    }

    private function createVariantsForProduct($product, $variants, $entrepot): void
    {
        foreach ($variants as $variantData) {
            $attributeName = $variantData['type'];
            $values = $variantData['values'];
            $stockEach = $variantData['stock_each'];
            $variantImages = $variantData['images'] ?? [];

            // Get the attribute
            $attribute = VariantAttribut::where('code', $attributeName)->first();
            if (!$attribute) continue;

            foreach ($values as $index => $value) {
                // Get the variant value
                $variantValue = VariantValeur::where('attribut_id', $attribute->id)
                    ->where('libelle', $value)
                    ->first();

                if (!$variantValue) continue;

                // Create product variant
                $productVariant = ProduitVariante::create([
                    'produit_id' => $product->id,
                    'nom' => $attributeName,
                    'valeur' => $value,
                    'image_url' => $variantImages[$value] ?? null,
                    'actif' => true,
                ]);

                // Create stock for this variant
                $stockQuantity = $stockEach[$index] ?? 0;
                if ($stockQuantity > 0) {
                    Stock::create([
                        'variante_id' => $productVariant->id,
                        'entrepot_id' => $entrepot->id,
                        'qte_disponible' => $stockQuantity,
                        'qte_reservee' => 0,
                    ]);
                }
            }
        }
    }
}
