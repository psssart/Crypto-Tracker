<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'hash' => '0x' . fake()->unique()->sha256(),
            'from_address' => '0x' . fake()->sha1(),
            'to_address' => '0x' . fake()->sha1(),
            'amount' => fake()->randomFloat(18, 0, 1000),
            'fee' => fake()->randomFloat(18, 0, 0.1),
            'block_number' => fake()->numberBetween(1000000, 20000000),
            'mined_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }
}
