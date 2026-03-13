<?php

namespace Database\Factories;

use App\Models\ReadingPlanProgress;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReadingPlanProgressFactory extends Factory
{
    protected $model = ReadingPlanProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => ReadingPlan::factory(),
            'start_date' => now()->subDays($this->faker->numberBetween(1, 30)),
            'current_day' => 1,
            'completed_days' => [],
            'is_completed' => false,
            'is_deleted' => false,
            'vector_clock' => [],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'is_completed' => true,
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn () => [
            'is_deleted' => true,
        ]);
    }
}
