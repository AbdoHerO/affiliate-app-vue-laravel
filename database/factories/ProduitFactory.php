<?php

namespace Database\Factories;

use App\Models\Produit;
use App\Models\Boutique;
use App\Models\Categorie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Produit>
 */
class ProduitFactory extends Factory
{
    protected $model = Produit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prixUnitaire = $this->faker->randomFloat(2, 20, 200);
        $prixRecommande = $prixUnitaire * $this->faker->randomFloat(2, 1.2, 2.0);

        return [
            'titre' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'prix_unitaire' => $prixUnitaire,
            'prix_recommande' => $prixRecommande,
            'boutique_id' => Boutique::factory(),
            'categorie_id' => Categorie::factory(),
            'actif' => true,
            'stock' => $this->faker->numberBetween(10, 100),
            'poids_kg' => $this->faker->randomFloat(2, 0.1, 5.0),
            'dimensions' => json_encode([
                'longueur' => $this->faker->numberBetween(10, 50),
                'largeur' => $this->faker->numberBetween(10, 50),
                'hauteur' => $this->faker->numberBetween(5, 30),
            ]),
            'meta' => json_encode([
                'tags' => $this->faker->words(3),
                'featured' => $this->faker->boolean(20),
            ]),
        ];
    }

    /**
     * Indicate that the product is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'actif' => true,
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'actif' => false,
        ]);
    }

    /**
     * Set specific pricing for the product.
     */
    public function withPricing(float $unitPrice, float $recommendedPrice): static
    {
        return $this->state(fn (array $attributes) => [
            'prix_unitaire' => $unitPrice,
            'prix_recommande' => $recommendedPrice,
        ]);
    }

    /**
     * Set the boutique for this product.
     */
    public function forBoutique(Boutique $boutique): static
    {
        return $this->state(fn (array $attributes) => [
            'boutique_id' => $boutique->id,
        ]);
    }

    /**
     * Set high stock for the product.
     */
    public function highStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(100, 500),
        ]);
    }

    /**
     * Set low stock for the product.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(1, 10),
        ]);
    }
}
