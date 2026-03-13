<?php

namespace Database\Factories;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['verse_of_day', 'reading_plan', 'new_module', 'sync']),
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(10),
            'data' => ['deep_link' => '/read/KJV/Gen/1'],
            'status' => 'sent',
        ];
    }

    public function verseOfDay(): static
    {
        return $this->state([
            'type' => 'verse_of_day',
            'title' => 'Verse of the Day',
            'body' => 'For God so loved the world... — John 3:16',
        ]);
    }

    public function readingPlan(): static
    {
        return $this->state([
            'type' => 'reading_plan',
            'title' => 'Reading Plan Reminder',
            'body' => 'Don\'t forget to read today\'s passage!',
        ]);
    }

    public function read(): static
    {
        return $this->state([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }
}
