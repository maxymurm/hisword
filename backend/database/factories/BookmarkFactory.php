<?php

namespace Database\Factories;

use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookmark>
 */
class BookmarkFactory extends Factory
{
    protected $model = Bookmark::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'folder_id'      => null,
            'book_osis_id'   => $this->faker->randomElement(['Gen', 'Exod', 'Psa', 'Isa', 'Matt', 'John', 'Rom', 'Rev']),
            'chapter_number' => $this->faker->numberBetween(1, 50),
            'verse_start'    => $this->faker->numberBetween(1, 30),
            'verse_end'      => null,
            'module_key'     => 'KJV',
            'label'          => $this->faker->sentence(4),
            'description'    => $this->faker->optional()->paragraph(),
            'sort_order'     => 0,
            'is_deleted'     => false,
            'vector_clock'   => [],
        ];
    }
}
