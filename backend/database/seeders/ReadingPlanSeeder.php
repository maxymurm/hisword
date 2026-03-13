<?php

namespace Database\Seeders;

use App\Models\ReadingPlan;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            $this->bibleInAYear(),
            $this->ntIn90Days(),
            $this->psalmsAndProverbs(),
            $this->gospels30Days(),
            $this->epistles60Days(),
        ];

        foreach ($plans as $plan) {
            ReadingPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan,
            );
        }
    }

    private function bibleInAYear(): array
    {
        $days = [];
        $otBooks = [
            ['book' => 'Gen', 'chapters' => 50], ['book' => 'Exod', 'chapters' => 40],
            ['book' => 'Lev', 'chapters' => 27], ['book' => 'Num', 'chapters' => 36],
            ['book' => 'Deut', 'chapters' => 34], ['book' => 'Josh', 'chapters' => 24],
            ['book' => 'Judg', 'chapters' => 21], ['book' => 'Ruth', 'chapters' => 4],
            ['book' => '1Sam', 'chapters' => 31], ['book' => '2Sam', 'chapters' => 24],
            ['book' => '1Kgs', 'chapters' => 22], ['book' => '2Kgs', 'chapters' => 25],
            ['book' => '1Chr', 'chapters' => 29], ['book' => '2Chr', 'chapters' => 36],
            ['book' => 'Ezra', 'chapters' => 10], ['book' => 'Neh', 'chapters' => 13],
            ['book' => 'Esth', 'chapters' => 10], ['book' => 'Job', 'chapters' => 42],
            ['book' => 'Ps', 'chapters' => 150], ['book' => 'Prov', 'chapters' => 31],
            ['book' => 'Eccl', 'chapters' => 12], ['book' => 'Song', 'chapters' => 8],
            ['book' => 'Isa', 'chapters' => 66], ['book' => 'Jer', 'chapters' => 52],
            ['book' => 'Lam', 'chapters' => 5], ['book' => 'Ezek', 'chapters' => 48],
            ['book' => 'Dan', 'chapters' => 12], ['book' => 'Hos', 'chapters' => 14],
            ['book' => 'Joel', 'chapters' => 3], ['book' => 'Amos', 'chapters' => 9],
            ['book' => 'Obad', 'chapters' => 1], ['book' => 'Jonah', 'chapters' => 4],
            ['book' => 'Mic', 'chapters' => 7], ['book' => 'Nah', 'chapters' => 3],
            ['book' => 'Hab', 'chapters' => 3], ['book' => 'Zeph', 'chapters' => 3],
            ['book' => 'Hag', 'chapters' => 2], ['book' => 'Zech', 'chapters' => 14],
            ['book' => 'Mal', 'chapters' => 4],
        ];

        // Distribute OT chapters across 365 days (~3.2 chapters/day)
        $allChapters = [];
        foreach ($otBooks as $b) {
            for ($ch = 1; $ch <= $b['chapters']; $ch++) {
                $allChapters[] = ['book' => $b['book'], 'chapter' => $ch];
            }
        }

        $ntBooks = [
            ['book' => 'Matt', 'chapters' => 28], ['book' => 'Mark', 'chapters' => 16],
            ['book' => 'Luke', 'chapters' => 24], ['book' => 'John', 'chapters' => 21],
            ['book' => 'Acts', 'chapters' => 28], ['book' => 'Rom', 'chapters' => 16],
            ['book' => '1Cor', 'chapters' => 16], ['book' => '2Cor', 'chapters' => 13],
            ['book' => 'Gal', 'chapters' => 6], ['book' => 'Eph', 'chapters' => 6],
            ['book' => 'Phil', 'chapters' => 4], ['book' => 'Col', 'chapters' => 4],
            ['book' => '1Thess', 'chapters' => 5], ['book' => '2Thess', 'chapters' => 3],
            ['book' => '1Tim', 'chapters' => 6], ['book' => '2Tim', 'chapters' => 4],
            ['book' => 'Titus', 'chapters' => 3], ['book' => 'Phlm', 'chapters' => 1],
            ['book' => 'Heb', 'chapters' => 13], ['book' => 'Jas', 'chapters' => 5],
            ['book' => '1Pet', 'chapters' => 5], ['book' => '2Pet', 'chapters' => 3],
            ['book' => '1John', 'chapters' => 5], ['book' => '2John', 'chapters' => 1],
            ['book' => '3John', 'chapters' => 1], ['book' => 'Jude', 'chapters' => 1],
            ['book' => 'Rev', 'chapters' => 22],
        ];

        foreach ($ntBooks as $b) {
            for ($ch = 1; $ch <= $b['chapters']; $ch++) {
                $allChapters[] = ['book' => $b['book'], 'chapter' => $ch];
            }
        }

        $totalChapters = count($allChapters);
        $perDay = (int) ceil($totalChapters / 365);

        for ($day = 1; $day <= 365; $day++) {
            $start = ($day - 1) * $perDay;
            $dayChapters = array_slice($allChapters, $start, $perDay);

            if (empty($dayChapters)) {
                break;
            }

            $readings = [];
            foreach ($dayChapters as $ch) {
                $readings[] = [
                    'book' => $ch['book'],
                    'chapter_start' => $ch['chapter'],
                    'chapter_end' => $ch['chapter'],
                ];
            }

            $days[] = ['day' => $day, 'readings' => $readings];
        }

        return [
            'name' => 'Bible in a Year',
            'description' => 'Read through the entire Bible in 365 days, covering both Old and New Testaments with approximately 3-4 chapters per day.',
            'duration_days' => 365,
            'plan_data' => $days,
            'is_system' => true,
        ];
    }

    private function ntIn90Days(): array
    {
        $books = [
            ['book' => 'Matt', 'chapters' => 28], ['book' => 'Mark', 'chapters' => 16],
            ['book' => 'Luke', 'chapters' => 24], ['book' => 'John', 'chapters' => 21],
            ['book' => 'Acts', 'chapters' => 28], ['book' => 'Rom', 'chapters' => 16],
            ['book' => '1Cor', 'chapters' => 16], ['book' => '2Cor', 'chapters' => 13],
            ['book' => 'Gal', 'chapters' => 6], ['book' => 'Eph', 'chapters' => 6],
            ['book' => 'Phil', 'chapters' => 4], ['book' => 'Col', 'chapters' => 4],
            ['book' => '1Thess', 'chapters' => 5], ['book' => '2Thess', 'chapters' => 3],
            ['book' => '1Tim', 'chapters' => 6], ['book' => '2Tim', 'chapters' => 4],
            ['book' => 'Titus', 'chapters' => 3], ['book' => 'Phlm', 'chapters' => 1],
            ['book' => 'Heb', 'chapters' => 13], ['book' => 'Jas', 'chapters' => 5],
            ['book' => '1Pet', 'chapters' => 5], ['book' => '2Pet', 'chapters' => 3],
            ['book' => '1John', 'chapters' => 5], ['book' => '2John', 'chapters' => 1],
            ['book' => '3John', 'chapters' => 1], ['book' => 'Jude', 'chapters' => 1],
            ['book' => 'Rev', 'chapters' => 22],
        ];

        $allChapters = [];
        foreach ($books as $b) {
            for ($ch = 1; $ch <= $b['chapters']; $ch++) {
                $allChapters[] = ['book' => $b['book'], 'chapter' => $ch];
            }
        }

        $perDay = (int) ceil(count($allChapters) / 90);
        $days = [];

        for ($day = 1; $day <= 90; $day++) {
            $start = ($day - 1) * $perDay;
            $dayChapters = array_slice($allChapters, $start, $perDay);

            if (empty($dayChapters)) break;

            $readings = [];
            foreach ($dayChapters as $ch) {
                $readings[] = ['book' => $ch['book'], 'chapter_start' => $ch['chapter'], 'chapter_end' => $ch['chapter']];
            }
            $days[] = ['day' => $day, 'readings' => $readings];
        }

        return [
            'name' => 'New Testament in 90 Days',
            'description' => 'Read the entire New Testament in just 90 days, about 3 chapters per day.',
            'duration_days' => 90,
            'plan_data' => $days,
            'is_system' => true,
        ];
    }

    private function psalmsAndProverbs(): array
    {
        $days = [];

        for ($day = 1; $day <= 31; $day++) {
            $readings = [];
            // 5 Psalms per day (31 days × 5 = 155, enough for all 150)
            for ($i = 0; $i < 5; $i++) {
                $psalmNum = ($day - 1) * 5 + $i + 1;
                if ($psalmNum <= 150) {
                    $readings[] = ['book' => 'Ps', 'chapter_start' => $psalmNum, 'chapter_end' => $psalmNum];
                }
            }
            // 1 Proverb per day
            if ($day <= 31) {
                $readings[] = ['book' => 'Prov', 'chapter_start' => $day, 'chapter_end' => $day];
            }
            $days[] = ['day' => $day, 'readings' => $readings];
        }

        return [
            'name' => 'Psalms & Proverbs in a Month',
            'description' => 'Read all 150 Psalms and 31 Proverbs in one month. Five Psalms and one Proverb per day.',
            'duration_days' => 31,
            'plan_data' => $days,
            'is_system' => true,
        ];
    }

    private function gospels30Days(): array
    {
        $books = [
            ['book' => 'Matt', 'chapters' => 28],
            ['book' => 'Mark', 'chapters' => 16],
            ['book' => 'Luke', 'chapters' => 24],
            ['book' => 'John', 'chapters' => 21],
        ];

        $allChapters = [];
        foreach ($books as $b) {
            for ($ch = 1; $ch <= $b['chapters']; $ch++) {
                $allChapters[] = ['book' => $b['book'], 'chapter' => $ch];
            }
        }

        $perDay = (int) ceil(count($allChapters) / 30);
        $days = [];

        for ($day = 1; $day <= 30; $day++) {
            $start = ($day - 1) * $perDay;
            $dayChapters = array_slice($allChapters, $start, $perDay);

            if (empty($dayChapters)) break;

            $readings = [];
            foreach ($dayChapters as $ch) {
                $readings[] = ['book' => $ch['book'], 'chapter_start' => $ch['chapter'], 'chapter_end' => $ch['chapter']];
            }
            $days[] = ['day' => $day, 'readings' => $readings];
        }

        return [
            'name' => 'The Gospels in 30 Days',
            'description' => 'Journey through Matthew, Mark, Luke, and John in 30 days. About 3 chapters per day.',
            'duration_days' => 30,
            'plan_data' => $days,
            'is_system' => true,
        ];
    }

    private function epistles60Days(): array
    {
        $books = [
            ['book' => 'Rom', 'chapters' => 16], ['book' => '1Cor', 'chapters' => 16],
            ['book' => '2Cor', 'chapters' => 13], ['book' => 'Gal', 'chapters' => 6],
            ['book' => 'Eph', 'chapters' => 6], ['book' => 'Phil', 'chapters' => 4],
            ['book' => 'Col', 'chapters' => 4], ['book' => '1Thess', 'chapters' => 5],
            ['book' => '2Thess', 'chapters' => 3], ['book' => '1Tim', 'chapters' => 6],
            ['book' => '2Tim', 'chapters' => 4], ['book' => 'Titus', 'chapters' => 3],
            ['book' => 'Phlm', 'chapters' => 1], ['book' => 'Heb', 'chapters' => 13],
            ['book' => 'Jas', 'chapters' => 5], ['book' => '1Pet', 'chapters' => 5],
            ['book' => '2Pet', 'chapters' => 3], ['book' => '1John', 'chapters' => 5],
            ['book' => '2John', 'chapters' => 1], ['book' => '3John', 'chapters' => 1],
            ['book' => 'Jude', 'chapters' => 1],
        ];

        $allChapters = [];
        foreach ($books as $b) {
            for ($ch = 1; $ch <= $b['chapters']; $ch++) {
                $allChapters[] = ['book' => $b['book'], 'chapter' => $ch];
            }
        }

        $perDay = max(1, (int) ceil(count($allChapters) / 60));
        $days = [];

        for ($day = 1; $day <= 60; $day++) {
            $start = ($day - 1) * $perDay;
            $dayChapters = array_slice($allChapters, $start, $perDay);

            if (empty($dayChapters)) break;

            $readings = [];
            foreach ($dayChapters as $ch) {
                $readings[] = ['book' => $ch['book'], 'chapter_start' => $ch['chapter'], 'chapter_end' => $ch['chapter']];
            }
            $days[] = ['day' => $day, 'readings' => $readings];
        }

        return [
            'name' => 'Epistles in 60 Days',
            'description' => 'Study all the New Testament epistles from Romans through Jude in 60 days.',
            'duration_days' => 60,
            'plan_data' => $days,
            'is_system' => true,
        ];
    }
}
