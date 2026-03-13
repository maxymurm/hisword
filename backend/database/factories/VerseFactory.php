<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\Verse;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerseFactory extends Factory
{
    protected $model = Verse::class;

    public function definition(): array
    {
        return [
            'module_id'       => Module::factory(),
            'book_osis_id'    => 'Gen',
            'chapter_number'  => 1,
            'verse_number'    => $this->faker->numberBetween(1, 31),
            'text_raw'        => $this->faker->sentence(12),
            'text_rendered'   => $this->faker->sentence(12),
            'strongs_data'    => [],
            'morphology_data' => [],
            'footnotes'       => [],
            'cross_refs'      => [],
        ];
    }
}
