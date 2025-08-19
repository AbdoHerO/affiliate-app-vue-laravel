<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Produit;
use App\Models\ProduitImage;
use App\Models\ProduitVariante;
use App\Models\Stock;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\VariantAttribut;
use App\Models\VariantValeur;

class RealisticProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Creating realistic products with proper variants...');

        // Get required data
        $boutique = Boutique::first();
        $category = Categorie::first();
        $entrepot = \App\Models\Entrepot::first();

        if (!$boutique || !$category || !$entrepot) {
            $this->command->error('Missing required data: boutique, category, or entrepot');
            return;
        }

        // Get variant attributes
        $tailleAttr = VariantAttribut::where('code', 'taille')->first();
        $couleurAttr = VariantAttribut::where('code', 'couleur')->first();

        if (!$tailleAttr || !$couleurAttr) {
            $this->command->error('Missing variant attributes: taille or couleur');
            return;
        }

        // Create products with realistic data
        $products = [
            [
                'titre' => 'Robe Élégante Soirée',
                'description' => 'Robe élégante parfaite pour les occasions spéciales. Tissu de qualité premium avec finitions soignées.',
                'prix_achat' => 220.00,
                'prix_vente' => 380.00,
                'prix_affilie' => 90.00,
                'images' => [
                    'https://images.unsplash.com/photo-1566479179817-c0b5b4b8b1cc?w=500&h=600&fit=crop',
                    'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=500&h=600&fit=crop',
                ],
                'variants' => [
                    'sizes' => ['S', 'M', 'L', 'XL'],
                    'colors' => [
                        'Rouge' => 'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=500&h=600&fit=crop',
                        'Noir' => 'https://images.unsplash.com/photo-1566479179817-c0b5b4b8b1cc?w=500&h=600&fit=crop',
                        'Bleu' => 'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?w=500&h=600&fit=crop',
                    ]
                ]
            ],
            [
                'titre' => 'Sneakers Urbain Style',
                'description' => 'Sneakers modernes et confortables pour un style urbain décontracté.',
                'prix_achat' => 180.00,
                'prix_vente' => 320.00,
                'prix_affilie' => 75.00,
                'images' => [
                    'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=500&h=500&fit=crop',
                    'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=500&h=500&fit=crop',
                ],
                'variants' => [
                    'sizes' => ['39', '40', '41', '42', '43'],
                    'colors' => [
                        'Blanc' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=500&h=500&fit=crop',
                        'Noir' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=500&h=500&fit=crop',
                        'Gris' => 'https://images.unsplash.com/photo-1560769629-975ec94e6a86?w=500&h=500&fit=crop',
                    ]
                ]
            ],
            [
                'titre' => 'Chemise Business Homme',
                'description' => 'Chemise élégante pour homme, parfaite pour le bureau et les occasions formelles.',
                'prix_achat' => 120.00,
                'prix_vente' => 220.00,
                'prix_affilie' => 55.00,
                'images' => [
                    'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=500&h=600&fit=crop',
                    'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?w=500&h=600&fit=crop',
                ],
                'variants' => [
                    'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                    'colors' => [
                        'Blanc' => 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=500&h=600&fit=crop',
                        'Bleu' => 'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?w=500&h=600&fit=crop',
                        'Noir' => 'https://images.unsplash.com/photo-1603252109303-2751441dd157?w=500&h=600&fit=crop',
                    ]
                ]
            ],
            [
                'titre' => 'Sac à Main Élégant',
                'description' => 'Sac à main élégant en cuir synthétique de qualité. Design moderne avec plusieurs compartiments.',
                'prix_achat' => 150.00,
                'prix_vente' => 280.00,
                'prix_affilie' => 70.00,
                'images' => [
                    'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=500&h=500&fit=crop',
                    'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=500&h=500&fit=crop',
                ],
                'variants' => [
                    'sizes' => [], // No sizes for bags
                    'colors' => [
                        'Noir' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=500&h=500&fit=crop',
                        'Beige' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=500&h=500&fit=crop',
                        'Rouge' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=500&h=500&fit=crop',
                    ]
                ]
            ],
            [
                'titre' => 'Montre Connectée Sport',
                'description' => 'Montre connectée avec suivi fitness avancé, GPS intégré et résistance à l\'eau.',
                'prix_achat' => 250.00,
                'prix_vente' => 450.00,
                'prix_affilie' => 120.00,
                'images' => [
                    'https://images.unsplash.com/photo-1579586337278-3f436f25d4d4?w=500&h=500&fit=crop',
                    'https://images.unsplash.com/photo-1544117519-31a4b719223d?w=500&h=500&fit=crop',
                ],
                'variants' => [
                    'sizes' => [], // No sizes for watches
                    'colors' => [
                        'Noir' => 'https://images.unsplash.com/photo-1579586337278-3f436f25d4d4?w=500&h=500&fit=crop',
                        'Blanc' => 'https://images.unsplash.com/photo-1544117519-31a4b719223d?w=500&h=500&fit=crop',
                        'Rose' => 'https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=500&h=500&fit=crop',
                    ]
                ]
            ]
        ];

        foreach ($products as $productData) {
            $this->createProductWithVariants($productData, $boutique, $category, $entrepot, $tailleAttr, $couleurAttr);
        }

        $this->command->info('✅ Realistic products created successfully!');
    }

    private function createProductWithVariants($data, $boutique, $category, $entrepot, $tailleAttr, $couleurAttr)
    {
        // Create product
        $product = Produit::create([
            'boutique_id' => $boutique->id,
            'categorie_id' => $category->id,
            'titre' => $data['titre'],
            'description' => $data['description'],
            'prix_achat' => $data['prix_achat'],
            'prix_vente' => $data['prix_vente'],
            'prix_affilie' => $data['prix_affilie'],
            'slug' => Str::slug($data['titre']) . '-' . time() . '-' . rand(100, 999),
            'actif' => true,
            'quantite_min' => 1,
            'notes_admin' => 'Produit avec variants réalistes',
            'rating_value' => rand(40, 50) / 10, // 4.0 to 5.0
        ]);

        // Create main product images
        foreach ($data['images'] as $index => $imageUrl) {
            ProduitImage::create([
                'produit_id' => $product->id,
                'url' => $imageUrl,
                'ordre' => $index + 1,
            ]);
        }

        // Create individual size variants (if any)
        if (!empty($data['variants']['sizes'])) {
            foreach ($data['variants']['sizes'] as $size) {
                $this->createSizeVariant($product, $tailleAttr, $size, $entrepot);
            }
        }

        // Create individual color variants (if any)
        if (!empty($data['variants']['colors'])) {
            foreach ($data['variants']['colors'] as $colorName => $colorImage) {
                $this->createColorVariant($product, $couleurAttr, $colorName, $colorImage, $entrepot);
            }
        }

        $this->command->info("✅ Created product: {$product->titre}");
    }

    private function createSizeVariant($product, $tailleAttr, $size, $entrepot)
    {
        // Get size value from catalog
        $sizeValue = VariantValeur::where('attribut_id', $tailleAttr->id)
            ->where('libelle', $size)
            ->first();

        if (!$sizeValue) {
            $this->command->warn("Missing size value: {$size}");
            return;
        }

        // Create size variant
        $sizeVariant = ProduitVariante::create([
            'produit_id' => $product->id,
            'nom' => 'taille',
            'valeur' => $size,
            'attribut_id' => $tailleAttr->id,
            'valeur_id' => $sizeValue->id,
            'actif' => true,
        ]);

        // Create stock for size variant
        Stock::create([
            'variante_id' => $sizeVariant->id,
            'entrepot_id' => $entrepot->id,
            'qte_disponible' => rand(20, 40),
            'qte_reservee' => 0,
        ]);
    }

    private function createColorVariant($product, $couleurAttr, $colorName, $colorImage, $entrepot)
    {
        // Get color value from catalog
        $colorValue = VariantValeur::where('attribut_id', $couleurAttr->id)
            ->where('libelle', $colorName)
            ->first();

        if (!$colorValue) {
            $this->command->warn("Missing color value: {$colorName}");
            return;
        }

        // Create color variant with image
        $colorVariant = ProduitVariante::create([
            'produit_id' => $product->id,
            'nom' => 'couleur',
            'valeur' => $colorName,
            'attribut_id' => $couleurAttr->id,
            'valeur_id' => $colorValue->id,
            'image_url' => $colorImage,
            'actif' => true,
        ]);

        // Create stock for color variant
        Stock::create([
            'variante_id' => $colorVariant->id,
            'entrepot_id' => $entrepot->id,
            'qte_disponible' => rand(25, 45),
            'qte_reservee' => 0,
        ]);
    }
}
