<?php

namespace Database\Factories;

use App\Models\Network;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Wallet> */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'network_id' => Network::factory(),
            'address' => '0x' . fake()->sha1(),
            'is_whale' => false,
            'metadata' => null,
            'balance_usd' => fake()->randomFloat(8, 0, 100000),
            'last_synced_at' => null,
        ];
    }

    public function whale(): static
    {
        return $this->state(fn () => [
            'is_whale' => true,
            'balance_usd' => fake()->randomFloat(8, 1000000, 100000000),
        ]);
    }

    public function synced(): static
    {
        return $this->state(fn () => [
            'last_synced_at' => now(),
        ]);
    }
}
