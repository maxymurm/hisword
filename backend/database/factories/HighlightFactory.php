<?php

namespace Database\Factories;

use App\Enums\HighlightColor;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HighlightFactory extends Factory
{
    protected $model = Highlight::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'book_osis_id'   => 'Gen',
            'chapter_number' => 1,
            'verse_number'   => $this->faker->numberBetween(1, 31),
            'color'          => $this->faker->randomElement(HighlightColor::cases()),
            'module_key'     => 'KJV',
            'is_deleted'     => false,
            'vector_clock'   => [],
        ];
    }
}
