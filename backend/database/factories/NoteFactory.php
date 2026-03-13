<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'book_osis_id'   => 'Gen',
            'chapter_number' => 1,
            'verse_start'    => $this->faker->numberBetween(1, 31),
            'module_key'     => 'KJV',
            'title'          => $this->faker->sentence(3),
            'content'        => $this->faker->paragraph(),
            'content_format' => 'markdown',
            'is_public'      => false,
            'is_deleted'     => false,
            'vector_clock'   => [],
        ];
    }
}
