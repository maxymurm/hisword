<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AudioBible;
use Illuminate\Http\JsonResponse;

class AudioController extends Controller
{
    /**
     * GET /audio/{module}/{book}/{chapter}
     *
     * Returns audio stream URL and metadata for the web player.
     */
    public function stream(string $module, string $book, int $chapter): JsonResponse
    {
        $audio = AudioBible::active()
            ->where('module_key', $module)
            ->where('book_osis_id', $book)
            ->where('chapter_number', $chapter)
            ->first();

        if (!$audio) {
            return response()->json(['data' => null], 404);
        }

        return response()->json([
            'data' => [
                'id' => $audio->id,
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
     * GET /audio/check/{module}/{book}/{chapter}
     *
     * Quick check if audio is available (lightweight, no URL generation).
     */
    public function check(string $module, string $book, int $chapter): JsonResponse
    {
        $exists = AudioBible::active()
            ->where('module_key', $module)
            ->where('book_osis_id', $book)
            ->where('chapter_number', $chapter)
            ->exists();

        return response()->json(['available' => $exists]);
    }
}
