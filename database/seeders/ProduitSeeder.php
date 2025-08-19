<?php

namespace Database\Seeders;

use App\Models\Produit;
use App\Models\ProduitImage;
use App\Models\ProduitVideo;
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

        // Create variant attributes if they don't exist
        $this->createVariantAttributes();

        $this->createProductsWithVariants($boutiques, $categories);

        $this->command->info('Produit seeder completed successfully!');
    }

    private function createVariantAttributes(): void
    {
        // Create size attribute if it doesn't exist
        $sizeAttr = VariantAttribut::firstOrCreate([
            'nom' => 'taille',
            'type' => 'select',
            'actif' => true
        ]);

        // Create color attribute if it doesn't exist
        $colorAttr = VariantAttribut::firstOrCreate([
            'nom' => 'couleur',
            'type' => 'color',
            'actif' => true
        ]);

        // Create size values
        $sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
        foreach ($sizes as $size) {
            VariantValeur::firstOrCreate([
                'attribut_id' => $sizeAttr->id,
                'valeur' => $size,
                'actif' => true
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
        ];

        foreach ($colors as $color) {
            VariantValeur::firstOrCreate([
                'attribut_id' => $colorAttr->id,
                'valeur' => $color['name'],
                'color' => $color['hex'],
                'actif' => true
            ]);
        }
    }

    private function createProductsWithVariants($boutiques, $categories): void
    {
        $products = [
            [
                'titre' => 'T-Shirt Premium Coton',
                'description' => 'T-shirt en coton bio de haute qualité, confortable et durable. Parfait pour un usage quotidien.',
                'prix_achat' => 25.00,
                'prix_vente' => 45.00,
                'prix_affilie' => 15.00,
                'actif' => true,
                'qte_min' => 5,
                'notes' => 'Produit populaire, maintenir un bon stock',
                'rating_value' => 4.5,
                'images' => [
                    'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400',
                    'https://images.unsplash.com/photo-1503341504253-dff4815485f1?w=400',
                    'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=400',
                ],
                'variants' => [
                    ['type' => 'taille', 'values' => ['S', 'M', 'L', 'XL'], 'stock_each' => [15, 25, 20, 10]],
                    ['type' => 'couleur', 'values' => ['Rouge', 'Bleu', 'Noir', 'Blanc'], 'stock_each' => [12, 18, 22, 8], 'images' => [
                        'Rouge' => 'https://images.unsplash.com/photo-1583743814966-8936f37f4678?w=400',
                        'Bleu' => 'https://images.unsplash.com/photo-1571945153237-4929e783af4a?w=400',
                        'Noir' => 'https://images.unsplash.com/photo-1503341504253-dff4815485f1?w=400',
                        'Blanc' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400',
                    ]],
                ]
            ],
            [
                'titre' => 'Sneakers Sport Moderne',
                'description' => 'Chaussures de sport modernes avec technologie de confort avancée. Idéales pour le running et le fitness.',
                'prix_achat' => 60.00,
                'prix_vente' => 120.00,
                'prix_affilie' => 35.00,
                'actif' => true,
                'qte_min' => 3,
                'notes' => 'Chaussures très demandées',
                'rating_value' => 4.8,
                'images' => [
                    'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=400',
                    'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=400',
                    'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=400',
                ],
                'variants' => [
                    ['type' => 'taille', 'values' => ['39', '40', '41', '42', '43', '44'], 'stock_each' => [8, 12, 15, 18, 10, 5]],
                    ['type' => 'couleur', 'values' => ['Noir', 'Blanc', 'Gris', 'Bleu'], 'stock_each' => [20, 15, 12, 8], 'images' => [
                        'Noir' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=400',
                        'Blanc' => 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=400',
                        'Gris' => 'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=400',
                        'Bleu' => 'https://images.unsplash.com/photo-1551107696-a4b0c5a0d9a2?w=400',
                    ]],
                ]
            ],
            [
                'titre' => 'Robe Élégante Soirée',
                'description' => 'Robe élégante parfaite pour les occasions spéciales. Tissu de qualité premium avec finitions soignées.',
                'prix_achat' => 80.00,
                'prix_vente' => 150.00,
                'prix_affilie' => 45.00,
                'actif' => true,
                'qte_min' => 2,
                'notes' => 'Produit saisonnier, forte demande',
                'rating_value' => 4.6,
                'images' => [
                    'https://images.unsplash.com/photo-1566479179817-c0b5b4b8b1cc?w=400',
                    'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=400',
                    'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?w=400',
                ],
                'variants' => [
                    ['type' => 'taille', 'values' => ['XS', 'S', 'M', 'L', 'XL'], 'stock_each' => [5, 12, 15, 10, 6]],
                    ['type' => 'couleur', 'values' => ['Noir', 'Rouge', 'Rose', 'Bleu'], 'stock_each' => [18, 12, 8, 10], 'images' => [
                        'Noir' => 'https://images.unsplash.com/photo-1566479179817-c0b5b4b8b1cc?w=400',
                        'Rouge' => 'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=400',
                        'Rose' => 'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?w=400',
                        'Bleu' => 'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?w=400',
                    ]],
                ]
            ],
            [
                'titre' => 'Sac à Main Cuir Premium',
                'description' => 'Sac à main en cuir véritable avec finitions artisanales. Design intemporel et fonctionnel.',
                'prix_achat' => 120.00,
                'prix_vente' => 220.00,
                'prix_affilie' => 65.00,
                'actif' => true,
                'qte_min' => 2,
                'notes' => 'Accessoire de luxe, marge élevée',
                'rating_value' => 4.7,
                'images' => [
                    'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400',
                    'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=400',
                    'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=400',
                ],
                'variants' => [
                    ['type' => 'couleur', 'values' => ['Noir', 'Marron', 'Rouge', 'Blanc'], 'stock_each' => [15, 12, 8, 5], 'images' => [
                        'Noir' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=400',
                        'Marron' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=400',
                        'Rouge' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=400',
                        'Blanc' => 'https://images.unsplash.com/photo-1591561954557-26941169b49e?w=400',
                    ]],
                ]
            ],
            [
                'titre' => 'Montre Connectée Sport',
                'description' => 'Montre connectée avec suivi fitness avancé, GPS intégré et résistance à l\'eau.',
                'prix_achat' => 150.00,
                'prix_vente' => 280.00,
                'prix_affilie' => 85.00,
                'actif' => true,
                'qte_min' => 3,
                'notes' => 'Technologie populaire, bon profit',
                'rating_value' => 4.4,
                'images' => [
                    'https://images.unsplash.com/photo-1579586337278-3f436f25d4d4?w=400',
                    'https://images.unsplash.com/photo-1544117519-31a4b719223d?w=400',
                    'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=400',
                ],
                'variants' => [
                    ['type' => 'couleur', 'values' => ['Noir', 'Blanc', 'Gris', 'Rose'], 'stock_each' => [20, 15, 10, 8], 'images' => [
                        'Noir' => 'https://images.unsplash.com/photo-1579586337278-3f436f25d4d4?w=400',
                        'Blanc' => 'https://images.unsplash.com/photo-1544117519-31a4b719223d?w=400',
                        'Gris' => 'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=400',
                        'Rose' => 'https://images.unsplash.com/photo-1434493789847-2f02dc6ca35d?w=400',
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
                'slug' => Str::slug($productData['titre']),
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
            $this->createVariantsForProduct($product, $variants);

            $this->command->info("Created product: {$product->titre}");
        }
    }

    private function createVariantsForProduct($product, $variants): void
    {
        foreach ($variants as $variantData) {
            $attributeName = $variantData['type'];
            $values = $variantData['values'];
            $stockEach = $variantData['stock_each'];
            $variantImages = $variantData['images'] ?? [];

            // Get the attribute
            $attribute = VariantAttribut::where('nom', $attributeName)->first();
            if (!$attribute) continue;

            foreach ($values as $index => $value) {
                // Get the variant value
                $variantValue = VariantValeur::where('attribut_id', $attribute->id)
                    ->where('valeur', $value)
                    ->first();

                if (!$variantValue) continue;

                // Create product variant
                $productVariant = ProduitVariante::create([
                    'produit_id' => $product->id,
                    'nom' => $attributeName,
                    'valeur' => $value,
                    'color' => $variantValue->color,
                    'image_url' => $variantImages[$value] ?? null,
                    'actif' => true,
                ]);

                // Create stock for this variant
                $stockQuantity = $stockEach[$index] ?? 0;
                if ($stockQuantity > 0) {
                    Stock::create([
                        'variante_id' => $productVariant->id,
                        'qte_disponible' => $stockQuantity,
                        'qte_reservee' => 0,
                        'seuil_alerte' => max(1, intval($stockQuantity * 0.2)), // 20% as alert threshold
                        'actif' => true,
                    ]);
                }
            }
        }
    }
}
