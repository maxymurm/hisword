<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AudioBible;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AudioController extends Controller
{
    /**
     * GET /api/v1/audio/{module}/{book}/{chapter}
     *
     * Return audio stream URL and metadata for a specific chapter.
     */
    public function stream(string $module, string $book, int $chapter): JsonResponse
    {
        $audio = AudioBible::active()
            ->where('module_key', $module)
            ->where('book_osis_id', $book)
            ->where('chapter_number', $chapter)
            ->first();

        if (!$audio) {
            return response()->json([
                'data' => null,
                'message' => 'No audio available for this chapter.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $audio->id,
                'module_key' => $audio->module_key,
                'book_osis_id' => $audio->book_osis_id,
                'chapter_number' => $audio->chapter_number,
                'stream_url' => $audio->getStreamUrl(),
                'duration' => $audio->duration,
                'formatted_duration' => $audio->formatted_duration,
                'format' => $audio->format,
                'narrator' => $audio->narrator,
                'verse_timings' => $audio->verse_timings,
            ],
        ]);
    }

    /**
     * GET /api/v1/audio/{module}/available
     *
     * List all available audio chapters for a module.
     */
    public function available(string $module): JsonResponse
    {
        $audios = AudioBible::active()
            ->where('module_key', $module)
            ->select(['id', 'book_osis_id', 'chapter_number', 'duration', 'narrator', 'format'])
            ->orderBy('book_osis_id')
            ->orderBy('chapter_number')
            ->get();

        return response()->json([
            'data' => $audios,
            'total' => $audios->count(),
        ]);
    }

    /**
     * GET /api/v1/audio/{module}/{book}
     *
     * List all audio chapters for a specific book.
     */
    public function bookChapters(string $module, string $book): JsonResponse
    {
        $audios = AudioBible::active()
            ->where('module_key', $module)
            ->where('book_osis_id', $book)
            ->select(['id', 'chapter_number', 'duration', 'narrator', 'format'])
            ->orderBy('chapter_number')
            ->get();

        return response()->json([
            'data' => $audios,
        ]);
    }

    /**
     * GET /api/v1/audio/next
     *
     * Get the next chapter's audio (auto-advance support).
     */
    public function next(Request $request): JsonResponse
    {
        $request->validate([
            'module' => 'required|string',
            'book' => 'required|string',
            'chapter' => 'required|integer|min:1',
        ]);

        $module = $request->query('module');
        $book = $request->query('book');
        $chapter = (int) $request->query('chapter');

        // Try next chapter in same book
        $next = AudioBible::active()
            ->where('module_key', $module)
            ->where('book_osis_id', $book)
            ->where('chapter_number', $chapter + 1)
            ->first();

        // If not found, try first chapter of next book
        if (!$next) {
            $booksOrder = [
                'Gen', 'Exod', 'Lev', 'Num', 'Deut', 'Josh', 'Judg', 'Ruth',
                '1Sam', '2Sam', '1Kgs', '2Kgs', '1Chr', '2Chr', 'Ezra', 'Neh',
                'Esth', 'Job', 'Ps', 'Prov', 'Eccl', 'Song', 'Isa', 'Jer',
                'Lam', 'Ezek', 'Dan', 'Hos', 'Joel', 'Amos', 'Obad', 'Jonah',
                'Mic', 'Nah', 'Hab', 'Zeph', 'Hag', 'Zech', 'Mal',
                'Matt', 'Mark', 'Luke', 'John', 'Acts', 'Rom', '1Cor', '2Cor',
                'Gal', 'Eph', 'Phil', 'Col', '1Thess', '2Thess', '1Tim', '2Tim',
                'Titus', 'Phlm', 'Heb', 'Jas', '1Pet', '2Pet', '1John', '2John',
                '3John', 'Jude', 'Rev',
            ];

            $currentIdx = array_search($book, $booksOrder);
            if ($currentIdx !== false && $currentIdx < count($booksOrder) - 1) {
                $nextBook = $booksOrder[$currentIdx + 1];
                $next = AudioBible::active()
                    ->where('module_key', $module)
                    ->where('book_osis_id', $nextBook)
                    ->where('chapter_number', 1)
                    ->first();
            }
        }

        if (!$next) {
            return response()->json(['data' => null], 404);
        }

        return response()->json([
            'data' => [
                'id' => $next->id,
                'module_key' => $next->module_key,
                'book_osis_id' => $next->book_osis_id,
                'chapter_number' => $next->chapter_number,
                'stream_url' => $next->getStreamUrl(),
                'duration' => $next->duration,
                'formatted_duration' => $next->formatted_duration,
                'format' => $next->format,
                'narrator' => $next->narrator,
                'verse_timings' => $next->verse_timings,
            ],
        ]);
    }
}
