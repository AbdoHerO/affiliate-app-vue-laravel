<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'mot_de_passe_hash' => Hash::make('oldpassword'),
            'statut' => 'actif',
        ]);
    }

    public function test_can_request_password_reset_link()
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);
    }

    public function test_cannot_request_password_reset_for_invalid_email()
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_cannot_request_password_reset_for_inactive_user()
    {
        $this->user->update(['statut' => 'inactif']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_can_reset_password_with_valid_token()
    {
        $token = Password::createToken($this->user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token' => $token,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

        // Verify password was changed
        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->mot_de_passe_hash));
    }

    public function test_cannot_reset_password_with_invalid_token()
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_can_validate_reset_token()
    {
        $token = Password::createToken($this->user);

        $response = $this->postJson('/api/auth/validate-reset-token', [
            'email' => $this->user->email,
            'token' => $token,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);
    }

    public function test_cannot_validate_invalid_reset_token()
    {
        $response = $this->postJson('/api/auth/validate-reset-token', [
            'email' => $this->user->email,
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                ]);
    }

    public function test_password_reset_requires_confirmation()
    {
        $token = Password::createToken($this->user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
            'token' => $token,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
    }

    public function test_password_reset_requires_minimum_length()
    {
        $token = Password::createToken($this->user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $this->user->email,
            'password' => '123',
            'password_confirmation' => '123',
            'token' => $token,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
    }
}
