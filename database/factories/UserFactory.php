<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom_complet' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verifie' => true,
            'mot_de_passe_hash' => static::$password ??= Hash::make('password'),
            'telephone' => fake()->phoneNumber(),
            'adresse' => fake()->address(),
            'statut' => 'actif',
            'kyc_statut' => 'non_requis',
            'approval_status' => 'pending_approval',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verifie' => false,
        ]);
    }
}
