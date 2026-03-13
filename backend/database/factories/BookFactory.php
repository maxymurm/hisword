<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'module_id'     => Module::factory(),
            'osis_id'       => $this->faker->unique()->randomElement([
                'Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth',
                'Matt', 'Mark', 'Luke', 'John', 'Acts', 'Rom', 'Rev',
            ]),
            'name'          => $this->faker->words(2, true),
            'abbreviation'  => $this->faker->lexify('???'),
            'testament'     => $this->faker->randomElement(['OT', 'NT']),
            'book_order'    => $this->faker->numberBetween(1, 66),
            'chapter_count' => $this->faker->numberBetween(1, 50),
        ];
    }
}
