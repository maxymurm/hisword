<?php

namespace Database\Factories;

use App\Models\ReadingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReadingPlanFactory extends Factory
{
    protected $model = ReadingPlan::class;

    public function definition(): array
    {
        $days = $this->faker->randomElement([7, 14, 30, 90, 365]);

        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'duration_days' => $days,
            'plan_data' => $this->generatePlanData($days),
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }

    private function generatePlanData(int $days): array
    {
        $books = ['Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth', 'Matt', 'Mark', 'Luke', 'John', 'Acts', 'Rom', 'Ps', 'Prov'];
        $data = [];

        for ($day = 1; $day <= min($days, 5); $day++) {
            $book = $books[array_rand($books)];
            $data[] = [
                'day' => $day,
                'readings' => [
                    ['book' => $book, 'chapter_start' => $day, 'chapter_end' => $day],
                ],
            ];
        }

        return $data;
    }
}
