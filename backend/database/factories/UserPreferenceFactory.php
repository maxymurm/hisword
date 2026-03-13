<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPreferenceFactory extends Factory
{
    protected $model = UserPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => fake()->unique()->word(),
            'value' => ['value' => fake()->word()],
            'vector_clock' => [],
        ];
    }
}
