<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Produit;
use App\Models\ProduitImage;
use App\Models\ProduitVideo;
use App\Models\ProduitVariante;
use App\Models\ProduitRupture;
use App\Models\ProduitProposition;
use App\Models\AvisProduit;
use App\Models\User;

class ComprehensiveProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a test boutique
        $boutique = Boutique::first();
        if (!$boutique) {
            $boutique = Boutique::create([
                'name' => 'Boutique Test',
                'slug' => 'boutique-test',
                'description' => 'Boutique de test pour les produits',
                'url' => 'https://boutique-test.com',
                'commission_rate' => 10.00,
                'status' => 'active',
                'owner' => 'Admin Test',
                'email' => 'test@boutique.com',
                'phone' => '+33123456789',
                'address' => '123 Rue de Test',
                'city' => 'Paris',
                'country' => 'France',
                'postal_code' => '75001',
            ]);
        }

        // Get or create a test category
        $category = Categorie::first();
        if (!$category) {
            $category = Categorie::create([
                'name' => 'Électronique',
                'slug' => 'electronique',
                'description' => 'Produits électroniques et gadgets',
                'status' => 'active',
                'order' => 1,
            ]);
        }

        // Get some users for reviews and propositions
        $users = User::take(5)->get();
        if ($users->count() < 3) {
            // Create test users if not enough exist
            for ($i = $users->count(); $i < 5; $i++) {
                $users = $users->concat([
                    User::create([
                        'nom_complet' => 'Test User ' . ($i + 1),
                        'email' => 'testuser' . ($i + 1) . '@example.com',
                        'mot_de_passe_hash' => bcrypt('password'),
                        'statut' => 'actif',
                    ])
                ]);
            }
        }

        // Check if products already exist
        if (Produit::count() > 0) {
            $this->command->info('Products already exist. Skipping seeding.');
            return;
        }

        // Sample products data
        $productsData = [
            [
                'name' => 'iPhone 15 Pro Max',
                'description' => 'Latest iPhone with advanced camera system and A17 Pro chip',
                'price_purchase' => 800.00,
                'price' => 1199.00,
                'quantity_min' => 5,
                'images' => [
                    'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=500',
                    'https://images.unsplash.com/photo-1565849904461-04a58ad377e0?w=500',
                    'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=500',
                ],
                'videos' => [
                    [
                        'title' => 'iPhone 15 Pro Review',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'description' => 'Comprehensive review of the iPhone 15 Pro features',
                    ],
                    [
                        'title' => 'Camera Test',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'description' => 'Testing the advanced camera system',
                    ],
                ],
                'variants' => [
                    ['name' => 'Color', 'value' => 'Natural Titanium', 'price' => null, 'sku' => 'IPH15P-NAT'],
                    ['name' => 'Color', 'value' => 'Blue Titanium', 'price' => null, 'sku' => 'IPH15P-BLU'],
                    ['name' => 'Color', 'value' => 'White Titanium', 'price' => null, 'sku' => 'IPH15P-WHT'],
                    ['name' => 'Storage', 'value' => '256GB', 'price' => null, 'sku' => 'IPH15P-256'],
                    ['name' => 'Storage', 'value' => '512GB', 'price' => 100.00, 'sku' => 'IPH15P-512'],
                    ['name' => 'Storage', 'value' => '1TB', 'price' => 300.00, 'sku' => 'IPH15P-1TB'],
                ],
                'propositions' => [
                    [
                        'titre' => 'Flash Sale Offer',
                        'description' => 'Limited time promotion with bonus commission',
                        'commission' => 15.00,
                    ],
                    [
                        'titre' => 'Premium Partnership',
                        'description' => 'Exclusive offer for premium affiliates',
                        'commission' => 20.00,
                    ],
                ],
            ],
            [
                'name' => 'MacBook Pro 16" M3',
                'description' => 'Professional laptop with M3 chip for creative professionals',
                'price_purchase' => 2000.00,
                'price' => 2899.00,
                'quantity_min' => 2,
                'images' => [
                    'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=500',
                    'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=500',
                ],
                'videos' => [
                    [
                        'title' => 'MacBook Pro M3 Performance',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'description' => 'Performance test of the new M3 chip',
                    ],
                ],
                'variants' => [
                    ['name' => 'Color', 'value' => 'Space Gray', 'price' => null, 'sku' => 'MBP16-SG'],
                    ['name' => 'Color', 'value' => 'Silver', 'price' => null, 'sku' => 'MBP16-SL'],
                    ['name' => 'RAM', 'value' => '18GB', 'price' => null, 'sku' => 'MBP16-18G'],
                    ['name' => 'RAM', 'value' => '36GB', 'price' => 400.00, 'sku' => 'MBP16-36G'],
                    ['name' => 'Storage', 'value' => '512GB SSD', 'price' => null, 'sku' => 'MBP16-512'],
                    ['name' => 'Storage', 'value' => '1TB SSD', 'price' => 300.00, 'sku' => 'MBP16-1TB'],
                ],
                'propositions' => [
                    [
                        'titre' => 'Creative Professional Bundle',
                        'description' => 'Special pricing for creative professionals',
                        'commission' => 12.00,
                    ],
                ],
            ],
            [
                'name' => 'AirPods Pro 3rd Gen',
                'description' => 'Premium wireless earbuds with advanced noise cancellation',
                'price_purchase' => 180.00,
                'price' => 279.00,
                'quantity_min' => 10,
                'images' => [
                    'https://images.unsplash.com/photo-1606220945770-b5b6c2c55bf1?w=500',
                    'https://images.unsplash.com/photo-1572569511254-d8f925fe2cbb?w=500',
                ],
                'videos' => [
                    [
                        'title' => 'AirPods Pro 3 Test',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'description' => 'Testing noise cancellation and audio quality',
                    ],
                ],
                'variants' => [
                    ['name' => 'Color', 'value' => 'White', 'price' => null, 'sku' => 'APP3-WH'],
                ],
                'propositions' => [
                    [
                        'titre' => 'Audio Bundle Deal',
                        'description' => 'Bundle with other audio accessories',
                        'commission' => 18.00,
                    ],
                ],
            ],
        ];

        foreach ($productsData as $productData) {
            // Create product
            $produit = Produit::create([
                'boutique_id' => $boutique->id,
                'categorie_id' => $category->id,
                'titre' => $productData['name'],
                'slug' => Str::slug($productData['name']),
                'description' => $productData['description'],
                'prix_achat' => $productData['price_purchase'],
                'prix_vente' => $productData['price'],
                'actif' => true,
                'quantite_min' => $productData['quantity_min'],
                'notes_admin' => 'Product created by comprehensive seeder',
            ]);

            // Create product images
            foreach ($productData['images'] as $index => $imageUrl) {
                ProduitImage::create([
                    'produit_id' => $produit->id,
                    'url' => $imageUrl,
                    'ordre' => $index + 1,
                ]);
            }

            // Create product videos
            foreach ($productData['videos'] as $index => $videoData) {
                ProduitVideo::create([
                    'produit_id' => $produit->id,
                    'titre' => $videoData['title'],
                    'url' => $videoData['url'],
                ]);
            }

            // Create product variants
            $createdVariants = [];
            foreach ($productData['variants'] as $variantData) {
                $variant = ProduitVariante::create([
                    'produit_id' => $produit->id,
                    'nom' => $variantData['name'],
                    'valeur' => $variantData['value'],
                    'prix_vente_variante' => $variantData['price'],
                    'actif' => true,
                ]);
                $createdVariants[] = $variant;
            }

            // Create rupture alerts for some variants
            if (!empty($createdVariants)) {
                foreach (array_slice($createdVariants, 0, rand(1, 2)) as $variant) {
                    ProduitRupture::create([
                        'produit_id' => $produit->id,
                        'variante_id' => $variant->id,
                        'motif' => 'Stock shortage detected',
                        'started_at' => now(),
                        'active' => true,
                    ]);
                }
            }

            // Create propositions
            foreach ($productData['propositions'] as $propData) {
                ProduitProposition::create([
                    'produit_id' => $produit->id,
                    'auteur_id' => $users->random()->id,
                    'type' => 'amelioration',
                    'description' => $propData['description'],
                    'statut' => 'approuve',
                ]);
            }

            // Create product reviews
            $reviewComments = [
                'Excellent product, very satisfied with my purchase!',
                'Great quality, highly recommend.',
                'Good value for money, fast delivery.',
                'Product as described, perfect.',
                'Very good purchase, no complaints.',
                'Outstanding quality and performance.',
                'Exceeded my expectations.',
            ];

            foreach ($users->take(rand(2, 4)) as $user) {
                AvisProduit::create([
                    'produit_id' => $produit->id,
                    'auteur_id' => $user->id,
                    'note' => rand(4, 5),
                    'commentaire' => $reviewComments[array_rand($reviewComments)],
                    'statut' => rand(0, 1) ? 'approuve' : 'en_attente',
                ]);
            }

            $this->command->info("✅ Created product: {$productData['name']} with all related data");
        }

        $this->command->info('🎉 Comprehensive product seeding completed successfully!');
        $this->command->info('   - ' . count($productsData) . ' products created');
        $this->command->info('   - Images, videos, variants, stock alerts');
        $this->command->info('   - Propositions and customer reviews');
        $this->command->info('   - Test boutique and category');
    }
}
