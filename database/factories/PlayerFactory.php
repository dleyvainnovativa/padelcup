<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'name' => fake('es_MX')->name(),
            'email' => fake()->optional(0.7)->safeEmail(),
            'phone' => fake()->optional(0.6)->numerify('55########'),
        ];
    }

    public function contactless(): static
    {
        return $this->state(fn() => ['email' => null, 'phone' => null]);
    }
}
