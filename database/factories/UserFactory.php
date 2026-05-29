<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'pin' => Hash::make('1234'),
            'balance' => 1000.00,
            'currency' => 'USD',
            'remember_token' => Str::random(10),
        ];
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
    }

    public function withPin(string $pin): static
    {
        return $this->state(fn (array $attributes) => [
            'pin' => Hash::make($pin),
        ]);
    }
}
