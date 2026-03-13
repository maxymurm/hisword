<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'key' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['bible', 'commentary', 'dictionary', 'genbook']),
            'language' => $this->faker->randomElement(['en', 'es', 'fr', 'de', 'ar', 'zh']),
            'version' => $this->faker->semver(),
            'source_url' => $this->faker->optional()->url(),
            'file_size' => $this->faker->optional()->numberBetween(1_000_000, 50_000_000),
            'is_installed' => false,
            'is_bundled' => false,
            'features' => [],
        ];
    }

    public function installed(): static
    {
        return $this->state(['is_installed' => true]);
    }

    public function bible(): static
    {
        return $this->state(['type' => 'bible']);
    }

    public function commentary(): static
    {
        return $this->state(['type' => 'commentary']);
    }

    public function dictionary(): static
    {
        return $this->state(['type' => 'dictionary']);
    }
}
