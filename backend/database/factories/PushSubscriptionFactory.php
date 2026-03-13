<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => fake()->randomElement(['android', 'ios', 'web']),
            'token' => fake()->sha256(),
            'preferences' => PushSubscription::defaultPreferences(),
            'timezone' => 'UTC',
            'is_active' => true,
        ];
    }

    public function android(): static
    {
        return $this->state(['platform' => 'android']);
    }

    public function ios(): static
    {
        return $this->state(['platform' => 'ios']);
    }

    public function web(): static
    {
        return $this->state(['platform' => 'web']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withQuietHours(string $start = '22:00', string $end = '07:00'): static
    {
        return $this->state([
            'quiet_hours_start' => $start,
            'quiet_hours_end' => $end,
        ]);
    }

    public function withDailyReminder(string $time = '08:00'): static
    {
        return $this->state([
            'daily_reminder_time' => $time,
        ]);
    }
}
