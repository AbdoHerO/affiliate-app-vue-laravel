<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categorie;

class CategorieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Traditional Moroccan clothing categories for production
        $categories = [
            [
                'nom' => 'Djellaba',
                'slug' => 'djellaba',
                'image_url' => 'https://images.unsplash.com/photo-1594736797933-d0401ba2fe65?w=400',
                'ordre' => 1,
                'actif' => true,
            ],
            [
                'nom' => 'Jabadour',
                'slug' => 'jabadour',
                'image_url' => 'https://images.unsplash.com/photo-1583391733956-6c78276477e2?w=400',
                'ordre' => 2,
                'actif' => true,
            ],
            [
                'nom' => 'Caftan',
                'slug' => 'caftan',
                'image_url' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=400',
                'ordre' => 3,
                'actif' => true,
            ],
            [
                'nom' => 'Takchita',
                'slug' => 'takchita',
                'image_url' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?w=400',
                'ordre' => 4,
                'actif' => true,
            ],
            [
                'nom' => 'Haik',
                'slug' => 'haik',
                'image_url' => 'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=400',
                'ordre' => 5,
                'actif' => true,
            ],
            [
                'nom' => 'Gandoura',
                'slug' => 'gandoura',
                'image_url' => 'https://images.unsplash.com/photo-1564859228273-274232fdb516?w=400',
                'ordre' => 6,
                'actif' => true,
            ],
            [
                'nom' => 'Accessories Traditionnels',
                'slug' => 'accessories-traditionnels',
                'image_url' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?w=400',
                'ordre' => 7,
                'actif' => true,
            ],
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
