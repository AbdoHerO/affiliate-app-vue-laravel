<?php

namespace Database\Factories;

use App\Models\Boutique;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Boutique>
 */
class BoutiqueFactory extends Factory
{
    protected $model = Boutique::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => $this->faker->company(),
            'adresse' => $this->faker->address(),
            'telephone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'actif' => true,
            'description' => $this->faker->optional()->paragraph(),
            'meta' => json_encode([
                'website' => $this->faker->optional()->url(),
                'social_media' => [
                    'facebook' => $this->faker->optional()->url(),
                    'instagram' => $this->faker->optional()->userName(),
                ],
            ]),
        ];
    }

    /**
     * Indicate that the boutique is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'actif' => true,
        ]);
    }

    /**
     * Indicate that the boutique is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'actif' => false,
        ]);
    }
}
