<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChapterFactory extends Factory
{
    protected $model = Chapter::class;

    public function definition(): array
    {
        return [
            'book_id'        => Book::factory(),
            'chapter_number' => $this->faker->numberBetween(1, 150),
            'verse_count'    => $this->faker->numberBetween(1, 40),
        ];
    }
}
