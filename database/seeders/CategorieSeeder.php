<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Categorie;
use Illuminate\Support\Str;

class CategorieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'nom' => 'Fashion & Clothing',
                'slug' => 'fashion-clothing',
                'image_url' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=400',
                'ordre' => 1,
                'actif' => true,
            ],
            [
                'nom' => 'Electronics',
                'slug' => 'electronics',
                'image_url' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?w=400',
                'ordre' => 2,
                'actif' => true,
            ],
            [
                'nom' => 'Home & Garden',
                'slug' => 'home-garden',
                'image_url' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400',
                'ordre' => 3,
                'actif' => true,
            ],
            [
                'nom' => 'Health & Beauty',
                'slug' => 'health-beauty',
                'image_url' => 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=400',
                'ordre' => 4,
                'actif' => true,
            ],
            [
                'nom' => 'Sports & Fitness',
                'slug' => 'sports-fitness',
                'image_url' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=400',
                'ordre' => 5,
                'actif' => true,
            ],
            [
                'nom' => 'Books & Education',
                'slug' => 'books-education',
                'image_url' => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=400',
                'ordre' => 6,
                'actif' => true,
            ],
            [
                'nom' => 'Toys & Games',
                'slug' => 'toys-games',
                'image_url' => 'https://images.unsplash.com/photo-1558060370-d644479cb6f7?w=400',
                'ordre' => 7,
                'actif' => true,
            ],
            [
                'nom' => 'Automotive',
                'slug' => 'automotive',
                'image_url' => 'https://images.unsplash.com/photo-1449824913935-59a10b8d2000?w=400',
                'ordre' => 8,
                'actif' => true,
            ],
            [
                'nom' => 'Food & Beverages',
                'slug' => 'food-beverages',
                'image_url' => 'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=400',
                'ordre' => 9,
                'actif' => true,
            ],
            [
                'nom' => 'Travel & Tourism',
                'slug' => 'travel-tourism',
                'image_url' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=400',
                'ordre' => 10,
                'actif' => false, // One inactive category for testing
            ]
        ];

        foreach ($categories as $categoryData) {
            Categorie::firstOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
        }

        $this->command->info('Categories seeded successfully!');
    }
}
