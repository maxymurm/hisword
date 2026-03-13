<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReadingPlanController extends Controller
{
    /**
     * Available reading plans with progress data.
     */
    public function index(Request $request): Response
    {
        $plans = self::getSamplePlans();

        return Inertia::render('Plans/Index', [
            'plans'  => $plans,
            'streak' => self::getStreakData(),
        ]);
    }

    /**
     * Individual reading plan detail.
     */
    public function show(string $slug): Response
    {
        $plans = self::getSamplePlans();
        $plan  = collect($plans)->firstWhere('slug', $slug);

        if (! $plan) {
            abort(404);
        }

        return Inertia::render('Plans/Show', [
            'plan' => $plan,
        ]);
    }

    // ── Sample Data ─────────────────────────────────────────────

    private static function getSamplePlans(): array
    {
        return [
            [
                'id'           => 1,
                'slug'         => 'bible-in-a-year',
                'title'        => 'Bible in a Year',
                'description'  => 'Read through the entire Bible in 365 days with Old Testament, New Testament, and Psalms/Proverbs readings each day.',
                'duration'     => 365,
                'category'     => 'Whole Bible',
                'daysCompleted'=> 42,
                'isActive'     => true,
                'startDate'    => '2025-01-01',
                'readings'     => self::bibleInAYearReadings(),
            ],
            [
                'id'           => 2,
                'slug'         => 'gospels-30-days',
                'title'        => 'Gospels in 30 Days',
                'description'  => 'A focused study through Matthew, Mark, Luke, and John.',
                'duration'     => 30,
                'category'     => 'New Testament',
                'daysCompleted'=> 30,
                'isActive'     => false,
                'startDate'    => '2024-12-01',
                'readings'     => self::gospels30Readings(),
            ],
            [
                'id'           => 3,
                'slug'         => 'psalms-31-days',
                'title'        => 'Psalms in 31 Days',
                'description'  => 'Read 5 Psalms each day to complete the book in one month.',
                'duration'     => 31,
                'category'     => 'Wisdom',
                'daysCompleted'=> 0,
                'isActive'     => false,
                'startDate'    => null,
                'readings'     => self::psalms31Readings(),
            ],
            [
                'id'           => 4,
                'slug'         => 'romans-14-days',
                'title'        => "Romans Deep Dive",
                'description'  => "Study Paul's letter to the Romans with 1-2 chapters per day.",
                'duration'     => 14,
                'category'     => 'Epistles',
                'daysCompleted'=> 0,
                'isActive'     => false,
                'startDate'    => null,
                'readings'     => self::romans14Readings(),
            ],
        ];
    }

    private static function getStreakData(): array
    {
        return [
            'current'    => 5,
            'longest'    => 14,
            'totalDays'  => 42,
            'thisWeek'   => [true, true, false, true, true, true, false],
        ];
    }

    private static function bibleInAYearReadings(): array
    {
        $readings = [];
        $otBooks = ['Gen 1-2', 'Gen 3-5', 'Gen 6-9', 'Gen 10-11', 'Gen 12-14', 'Gen 15-17', 'Gen 18-19', 'Gen 20-22', 'Gen 23-25', 'Gen 26-27', 'Gen 28-30', 'Gen 31-33', 'Gen 34-36', 'Gen 37-39', 'Gen 40-42', 'Gen 43-45', 'Gen 46-47', 'Gen 48-50', 'Exod 1-3', 'Exod 4-6'];
        $ntBooks  = ['Matt 1', 'Matt 2', 'Matt 3', 'Matt 4', 'Matt 5', 'Matt 6', 'Matt 7', 'Matt 8', 'Matt 9', 'Matt 10', 'Matt 11', 'Matt 12', 'Matt 13', 'Matt 14', 'Matt 15', 'Matt 16', 'Matt 17', 'Matt 18', 'Matt 19', 'Matt 20'];
        $psalms   = ['Ps 1-2', 'Ps 3-4', 'Ps 5-6', 'Ps 7-8', 'Ps 9-10', 'Ps 11-12', 'Ps 13-14', 'Ps 15-16', 'Ps 17', 'Ps 18', 'Ps 19', 'Ps 20-21', 'Ps 22', 'Ps 23-24', 'Ps 25', 'Ps 26-27', 'Ps 28-29', 'Ps 30', 'Ps 31', 'Ps 32'];

        for ($i = 0; $i < 50; $i++) {
            $readings[] = [
                'day'       => $i + 1,
                'passages'  => [
                    $otBooks[$i % count($otBooks)],
                    $ntBooks[$i % count($ntBooks)],
                    $psalms[$i % count($psalms)],
                ],
                'completed' => $i < 42,
            ];
        }

        return $readings;
    }

    private static function gospels30Readings(): array
    {
        $passages = [
            'Matt 1-2', 'Matt 3-4', 'Matt 5-7', 'Matt 8-9', 'Matt 10-11', 'Matt 12-13', 'Matt 14-16', 'Matt 17-19',
            'Matt 20-22', 'Matt 23-25', 'Matt 26-28', 'Mark 1-3', 'Mark 4-6', 'Mark 7-9', 'Mark 10-12', 'Mark 13-16',
            'Luke 1-2', 'Luke 3-4', 'Luke 5-7', 'Luke 8-9', 'Luke 10-12', 'Luke 13-16', 'Luke 17-19', 'Luke 20-22',
            'Luke 23-24', 'John 1-3', 'John 4-6', 'John 7-9', 'John 10-14', 'John 15-21',
        ];
        return array_map(fn($i) => [
            'day'       => $i + 1,
            'passages'  => [$passages[$i]],
            'completed' => true,
        ], range(0, 29));
    }

    private static function psalms31Readings(): array
    {
        return array_map(fn($i) => [
            'day'       => $i + 1,
            'passages'  => ['Ps ' . ($i * 5 + 1) . '-' . (($i + 1) * 5)],
            'completed' => false,
        ], range(0, 30));
    }

    private static function romans14Readings(): array
    {
        $passages = ['Rom 1', 'Rom 2', 'Rom 3', 'Rom 4', 'Rom 5', 'Rom 6', 'Rom 7', 'Rom 8:1-17', 'Rom 8:18-39', 'Rom 9', 'Rom 10-11', 'Rom 12', 'Rom 13-14', 'Rom 15-16'];
        return array_map(fn($i) => [
            'day'       => $i + 1,
            'passages'  => [$passages[$i]],
            'completed' => false,
        ], range(0, 13));
    }
}
