<?php

namespace Database\Factories;

use App\Models\ModuleSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleSourceFactory extends Factory
{
    protected $model = ModuleSource::class;

    public function definition(): array
    {
        return [
            'caption' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement(['FTP', 'HTTP']),
            'server' => $this->faker->domainName(),
            'directory' => '/pub/sword/packages',
            'is_active' => true,
            'last_refreshed' => $this->faker->optional()->dateTimeThisMonth(),
        ];
    }
}
