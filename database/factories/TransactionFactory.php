<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'withdraw',
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'fee_amount' => $this->faker->randomFloat(2, 0.1, 10),
            'balance_after' => $this->faker->randomFloat(2, 100, 10000),
            'created_at' => now(),
        ];
    }
}
