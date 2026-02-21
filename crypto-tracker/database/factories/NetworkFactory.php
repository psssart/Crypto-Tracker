<?php

namespace Database\Factories;

use App\Models\Network;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Network> */
class NetworkFactory extends Factory
{
    protected $model = Network::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(1),
            'chain_id' => fake()->numberBetween(1, 99999),
            'currency_symbol' => strtoupper(fake()->lexify('???')),
            'explorer_url' => fake()->url(),
            'is_active' => true,
        ];
    }

    public function ethereum(): static
    {
        return $this->state(fn () => [
            'name' => 'Ethereum',
            'slug' => 'ethereum',
            'chain_id' => 1,
            'currency_symbol' => 'ETH',
            'explorer_url' => 'https://etherscan.io',
        ]);
    }

    public function polygon(): static
    {
        return $this->state(fn () => [
            'name' => 'Polygon',
            'slug' => 'polygon',
            'chain_id' => 137,
            'currency_symbol' => 'MATIC',
            'explorer_url' => 'https://polygonscan.com',
        ]);
    }

    public function bsc(): static
    {
        return $this->state(fn () => [
            'name' => 'BNB Smart Chain',
            'slug' => 'bsc',
            'chain_id' => 56,
            'currency_symbol' => 'BNB',
            'explorer_url' => 'https://bscscan.com',
        ]);
    }

    public function solana(): static
    {
        return $this->state(fn () => [
            'name' => 'Solana',
            'slug' => 'solana',
            'chain_id' => null,
            'currency_symbol' => 'SOL',
            'explorer_url' => 'https://solscan.io',
        ]);
    }
}
