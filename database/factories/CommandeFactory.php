<?php

namespace Database\Factories;

use App\Models\Commande;
use App\Models\User;
use App\Models\Client;
use App\Models\Adresse;
use App\Models\Boutique;
use App\Models\Offre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commande>
 */
class CommandeFactory extends Factory
{
    protected $model = Commande::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'boutique_id' => Boutique::factory(),
            'user_id' => User::factory(),
            'client_id' => Client::factory(),
            'adresse_id' => Adresse::factory(),
            'offre_id' => Offre::factory(),
            'statut' => $this->faker->randomElement(['pending', 'confirmed', 'expediee', 'livree']),
            'confirmation_cc' => $this->faker->boolean(30),
            'mode_paiement' => 'cod',
            'total_ht' => $this->faker->randomFloat(2, 50, 500),
            'total_ttc' => function (array $attributes) {
                return $attributes['total_ht'];
            },
            'devise' => 'MAD',
            'notes' => $this->faker->optional()->sentence(),
            'no_answer_count' => 0,
        ];
    }

    /**
     * Indicate that the order is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'pending',
        ]);
    }

    /**
     * Indicate that the order is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'confirmed',
        ]);
    }

    /**
     * Indicate that the order is delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'livree',
        ]);
    }

    /**
     * Set the affiliate for this order.
     */
    public function forAffiliate(User $affiliate): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $affiliate->id,
        ]);
    }

    /**
     * Set the boutique for this order.
     */
    public function forBoutique(Boutique $boutique): static
    {
        return $this->state(fn (array $attributes) => [
            'boutique_id' => $boutique->id,
        ]);
    }

    /**
     * Set the offer for this order.
     */
    public function forOffer(Offre $offre): static
    {
        return $this->state(fn (array $attributes) => [
            'offre_id' => $offre->id,
        ]);
    }
}
