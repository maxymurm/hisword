<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatisticsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Statistics', [
            'bible_stats' => $this->getBibleStats(),
            'reading_stats' => $this->getReadingStats($request),
            'annotation_stats' => $this->getAnnotationStats($request),
        ]);
    }

    private function getBibleStats(): array
    {
        return [
            'total_books' => 66,
            'total_chapters' => 1189,
            'total_verses' => 31102,
            'total_words' => 783137,
            'ot' => [
                'books' => 39,
                'chapters' => 929,
                'verses' => 23145,
                'words' => 592439,
                'longest_book' => ['name' => 'Psalms', 'chapters' => 150, 'verses' => 2461],
                'shortest_book' => ['name' => 'Obadiah', 'chapters' => 1, 'verses' => 21],
            ],
            'nt' => [
                'books' => 27,
                'chapters' => 260,
                'verses' => 7957,
                'words' => 190698,
                'longest_book' => ['name' => 'Luke', 'chapters' => 24, 'verses' => 1151],
                'shortest_book' => ['name' => '3 John', 'chapters' => 1, 'verses' => 14],
            ],
            'longest_verse' => ['ref' => 'Esther 8:9', 'words' => 90],
            'shortest_verse' => ['ref' => 'John 11:35', 'words' => 2, 'text' => 'Jesus wept.'],
            'middle_verse' => ['ref' => 'Psalm 118:8', 'text' => 'It is better to trust in the LORD than to put confidence in man.'],
            'word_frequency' => [
                ['word' => 'the', 'count' => 63924],
                ['word' => 'and', 'count' => 51696],
                ['word' => 'of', 'count' => 34617],
                ['word' => 'to', 'count' => 13562],
                ['word' => 'LORD', 'count' => 7736],
                ['word' => 'God', 'count' => 4473],
                ['word' => 'shall', 'count' => 9838],
                ['word' => 'he', 'count' => 10420],
                ['word' => 'unto', 'count' => 8997],
                ['word' => 'for', 'count' => 8971],
            ],
        ];
    }

    private function getReadingStats(Request $request): array
    {
        if (!$request->user()) {
            return ['logged_in' => false];
        }

        // Sample reading stats – in production, this queries the history table
        return [
            'logged_in' => true,
            'chapters_read' => 342,
            'verses_read' => 8750,
            'total_reading_time_minutes' => 1230,
            'current_streak' => 12,
            'longest_streak' => 45,
            'favorite_book' => 'Psalms',
            'monthly_reading' => [
                ['month' => 'Jan', 'chapters' => 42],
                ['month' => 'Feb', 'chapters' => 38],
                ['month' => 'Mar', 'chapters' => 55],
                ['month' => 'Apr', 'chapters' => 31],
                ['month' => 'May', 'chapters' => 48],
                ['month' => 'Jun', 'chapters' => 25],
                ['month' => 'Jul', 'chapters' => 39],
                ['month' => 'Aug', 'chapters' => 44],
                ['month' => 'Sep', 'chapters' => 20],
                ['month' => 'Oct', 'chapters' => 0],
                ['month' => 'Nov', 'chapters' => 0],
                ['month' => 'Dec', 'chapters' => 0],
            ],
        ];
    }

    private function getAnnotationStats(Request $request): array
    {
        if (!$request->user()) {
            return ['logged_in' => false];
        }

        return [
            'logged_in' => true,
            'total_bookmarks' => 47,
            'total_notes' => 23,
            'total_highlights' => 156,
            'highlight_colors' => [
                ['color' => 'yellow', 'count' => 68],
                ['color' => 'green', 'count' => 42],
                ['color' => 'blue', 'count' => 28],
                ['color' => 'pink', 'count' => 12],
                ['color' => 'purple', 'count' => 6],
            ],
        ];
    }
}
