<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserIntegration>
 */
class UserIntegrationFactory extends Factory
{
    protected $model = UserIntegration::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['alltick', 'freecryptoapi', 'bybit', 'openai']),
            'api_key' => fake()->sha256(),
            'settings' => null,
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function provider(string $provider): static
    {
        return $this->state(fn () => ['provider' => $provider]);
    }

    public function alltick(): static
    {
        return $this->provider('alltick');
    }

    public function freecryptoapi(): static
    {
        return $this->provider('freecryptoapi');
    }

    public function bybit(): static
    {
        return $this->state(fn () => [
            'provider' => 'bybit',
            'settings' => ['api_secret' => fake()->sha256()],
        ]);
    }

    public function openai(): static
    {
        return $this->provider('openai');
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
        ]);
    }
}
