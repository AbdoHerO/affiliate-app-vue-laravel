<?php

namespace Database\Factories;

use App\Models\Offre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Offre>
 */
class OffreFactory extends Factory
{
    protected $model = Offre::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'actif' => true,
            'date_debut' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'date_fin' => $this->faker->optional()->dateTimeBetween('now', '+3 months'),
            'meta' => json_encode([
                'terms' => $this->faker->optional()->sentence(),
                'conditions' => $this->faker->optional()->paragraph(),
            ]),
        ];
    }

    /**
     * Indicate that the offer is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'actif' => true,
        ]);
    }

    /**
     * Indicate that the offer is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'actif' => false,
        ]);
    }

    /**
     * Set specific dates for the offer.
     */
    public function withDates(\DateTime $start, \DateTime $end): static
    {
        return $this->state(fn (array $attributes) => [
            'date_debut' => $start,
            'date_fin' => $end,
        ]);
    }
}
