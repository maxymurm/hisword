<?php

namespace Database\Factories;

use App\Models\Pin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pin>
 */
class PinFactory extends Factory
{
    protected $model = Pin::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'book_osis_id'   => $this->faker->randomElement(['Gen', 'Exod', 'Psa', 'Isa', 'Matt', 'John', 'Rom', 'Rev']),
            'chapter_number' => $this->faker->numberBetween(1, 50),
            'verse_number'   => $this->faker->numberBetween(1, 30),
            'module_key'     => 'KJV',
            'label'          => $this->faker->optional()->sentence(3),
            'sort_order'     => 0,
            'is_deleted'     => false,
            'vector_clock'   => [],
        ];
    }
}
