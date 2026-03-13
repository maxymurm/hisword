<?php

namespace Database\Factories;

use App\Models\AudioBible;
use Illuminate\Database\Eloquent\Factories\Factory;

class AudioBibleFactory extends Factory
{
    protected $model = AudioBible::class;

    public function definition(): array
    {
        $books = ['Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth',
                   'Matt', 'Mark', 'Luke', 'John', 'Acts', 'Rom', 'Rev'];
        $book = $this->faker->randomElement($books);
        $chapter = $this->faker->numberBetween(1, 30);

        return [
            'module_key' => 'KJV',
            'book_osis_id' => $book,
            'chapter_number' => $chapter,
            'storage_path' => "audio/KJV/{$book}/{$chapter}.mp3",
            'storage_disk' => 'public',
            'duration' => $this->faker->numberBetween(120, 600),
            'file_size' => $this->faker->numberBetween(500000, 5000000),
            'format' => 'mp3',
            'narrator' => $this->faker->name(),
            'language' => 'en',
            'verse_timings' => null,
            'is_active' => true,
        ];
    }

    public function withVerseTimings(int $verseCount = 20): static
    {
        return $this->state(function (array $attributes) use ($verseCount) {
            $timings = [];
            $currentTime = 0.0;
            for ($v = 1; $v <= $verseCount; $v++) {
                $segmentDuration = round(mt_rand(30, 150) / 10, 1);
                $timings[] = [
                    'verse' => $v,
                    'start' => $currentTime,
                    'end' => round($currentTime + $segmentDuration, 1),
                ];
                $currentTime = round($currentTime + $segmentDuration, 1);
            }

            return [
                'verse_timings' => $timings,
                'duration' => (int) ceil($currentTime),
            ];
        });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
