<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => $this->faker->uuid(),
            'platform' => $this->faker->randomElement(['android', 'ios', 'web']),
            'name' => $this->faker->randomElement(['Pixel 8', 'iPhone 15', 'Samsung Galaxy S24', 'iPad Pro', 'Chrome Browser']),
            'push_token' => $this->faker->optional()->sha256(),
            'last_sync_at' => $this->faker->optional()->dateTimeThisMonth(),
            'app_version' => $this->faker->optional()->semver(),
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
}
