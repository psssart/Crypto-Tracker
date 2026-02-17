<?php

namespace Database\Factories;

use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebhookLog> */
class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    public function definition(): array
    {
        return [
            'payload' => [
                'event' => fake()->randomElement(['transfer', 'swap', 'mint']),
                'chain' => fake()->randomElement(['ethereum', 'polygon', 'bsc']),
                'data' => ['tx_hash' => '0x' . fake()->sha256()],
            ],
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'processed_at' => now(),
        ]);
    }
}
