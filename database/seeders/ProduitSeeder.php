<?php

namespace Database\Seeders;

use App\Models\Produit;
use App\Models\ProduitImage;
use App\Models\Boutique;
use App\Models\Categorie;
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

        $products = [
            [
                'name' => 'iPhone 15 Pro',
                'description' => 'Latest iPhone with advanced features and powerful performance.',
                'price_purchase' => 999.00,
                'price' => 1199.00,
                'status' => 'active',
                'quantity_min' => 1,
                'notes' => 'High-demand product, maintain good stock levels',
                'images' => [
                    'https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=400',
                    'https://images.unsplash.com/photo-1695048077491-824c4ad447ba?w=400',
                ]
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'description' => 'Premium Android smartphone with excellent camera quality.',
                'price_purchase' => 850.00,
                'price' => 999.00,
                'status' => 'active',
                'quantity_min' => 2,
                'notes' => 'Popular alternative to iPhone',
                'images' => [
                    'https://images.unsplash.com/photo-1610945264303-20c97f8d1ead?w=400',
                ]
            ],
            [
                'name' => 'MacBook Air M3',
                'description' => 'Lightweight laptop with exceptional battery life and performance.',
                'price_purchase' => 1199.00,
                'price' => 1399.00,
                'status' => 'active',
                'quantity_min' => 1,
                'notes' => 'Great for students and professionals',
                'images' => [
                    'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=400',
                    'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=400',
                ]
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'description' => 'Premium noise-canceling wireless headphones.',
                'price_purchase' => 299.00,
                'price' => 399.00,
                'status' => 'active',
                'quantity_min' => 5,
                'notes' => 'Best-in-class noise cancellation',
                'images' => [
                    'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=400',
                ]
            ],
            [
                'name' => 'Apple Watch Series 9',
                'description' => 'Advanced smartwatch with health monitoring features.',
                'price_purchase' => 349.00,
                'price' => 429.00,
                'status' => 'active',
                'quantity_min' => 3,
                'notes' => 'Popular accessory for iPhone users',
                'images' => [
                    'https://images.unsplash.com/photo-1579586337278-3f436f25d4d4?w=400',
                    'https://images.unsplash.com/photo-1544117519-31a4b719223d?w=400',
                ]
            ],
            [
                'name' => 'iPad Pro 12.9"',
                'description' => 'Professional tablet for creative work and productivity.',
                'price_purchase' => 999.00,
                'price' => 1199.00,
                'status' => 'inactive',
                'quantity_min' => 1,
                'notes' => 'Temporarily out of stock',
                'images' => [
                    'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=400',
                ]
            ],
            [
                'name' => 'Nintendo Switch OLED',
                'description' => 'Popular gaming console with vibrant OLED display.',
                'price_purchase' => 299.00,
                'price' => 349.00,
                'status' => 'active',
                'quantity_min' => 2,
                'notes' => 'Great for families and gamers',
                'images' => [
                    'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=400',
                ]
            ],
            [
                'name' => 'Dell XPS 13',
                'description' => 'Compact Windows laptop with premium build quality.',
                'price_purchase' => 899.00,
                'price' => 1099.00,
                'status' => 'active',
                'quantity_min' => 1,
                'notes' => 'Business laptop alternative',
                'images' => [
                    'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=400',
                ]
            ],
        ];

        foreach ($products as $index => $productData) {
            $boutique = $boutiques->random();
            $category = $categories->random();
            
            $images = $productData['images'];
            unset($productData['images']);

            $product = Produit::create([
                'boutique_id' => $boutique->id,
                'category_id' => $category->id,
                'slug' => Str::slug($productData['name']),
                ...$productData
            ]);

            // Add images
            foreach ($images as $imageIndex => $imageUrl) {
                ProduitImage::create([
                    'produit_id' => $product->id,
                    'image_url' => $imageUrl,
                    'order' => $imageIndex + 1,
                ]);
            }

            $this->command->info("Created product: {$product->name}");
        }

        $this->command->info('Produit seeder completed successfully!');
    }
}
