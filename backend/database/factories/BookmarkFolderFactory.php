<?php

namespace Database\Factories;

use App\Models\BookmarkFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookmarkFolder>
 */
class BookmarkFolderFactory extends Factory
{
    protected $model = BookmarkFolder::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'parent_id'    => null,
            'name'         => $this->faker->word(),
            'color'        => $this->faker->hexColor(),
            'sort_order'   => 0,
            'is_deleted'   => false,
            'vector_clock' => [],
        ];
    }
}
