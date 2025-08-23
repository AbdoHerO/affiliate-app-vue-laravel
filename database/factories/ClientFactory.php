<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom_complet' => $this->faker->name(),
            'telephone' => $this->faker->phoneNumber(),
            'email' => $this->faker->optional()->safeEmail(),
            'date_naissance' => $this->faker->optional()->date(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the client has an email.
     */
    public function withEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $this->faker->safeEmail(),
        ]);
    }

    /**
     * Indicate that the client has no email.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }
}
