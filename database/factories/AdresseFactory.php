<?php

namespace Database\Factories;

use App\Models\Adresse;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Adresse>
 */
class AdresseFactory extends Factory
{
    protected $model = Adresse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = ['Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 'Oujda'];

        return [
            'client_id' => Client::factory(),
            'ville' => $this->faker->randomElement($cities),
            'adresse' => $this->faker->streetAddress(),
            'code_postal' => $this->faker->postcode(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Set the client for this address.
     */
    public function forClient(Client $client): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
        ]);
    }

    /**
     * Set a specific city for this address.
     */
    public function inCity(string $city): static
    {
        return $this->state(fn (array $attributes) => [
            'ville' => $city,
        ]);
    }
}
