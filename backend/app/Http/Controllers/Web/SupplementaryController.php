<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Verse;
use App\Services\CacheService;
use App\Services\Sword\SwordManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SupplementaryController extends Controller
{
    public function __construct(
        protected CacheService $cache,
        protected SwordManager $swordManager,
    ) {}

    /**
     * Get commentary entries for a given book/chapter.
     */
    public function commentary(string $moduleKey, string $book, int $chapter): JsonResponse
    {
        $module = Module::where('key', $moduleKey)
            ->where('type', 'commentary')
            ->where('is_installed', true)
            ->first();

        if (! $module) {
            return response()->json(['entries' => [], 'error' => 'Module not found'], 404);
        }

        // Try DB first
        $entries = $this->cache->verses($moduleKey, $book, $chapter, function () use ($module, $book, $chapter) {
            return Verse::where('module_id', $module->id)
                ->where('book_osis_id', $book)
                ->where('chapter_number', $chapter)
                ->orderBy('verse_number')
                ->get()
                ->map(fn ($v) => [
                    'verse'  => $v->verse_number,
                    'text'   => $v->text_rendered ?? $v->text_raw,
                ])
                ->toArray();
        });

        // Fallback: read directly from SWORD binary files
        if (empty($entries) && $module->data_path) {
            try {
                $rawVerses = $this->swordManager->readChapter($module, $book, $chapter);
                if (! empty($rawVerses)) {
                    $entries = collect($rawVerses)->map(fn ($data, $verseNum) => [
                        'verse' => $verseNum,
                        'text'  => is_array($data) ? ($data['html'] ?? $data['raw'] ?? '') : $data,
                    ])->values()->toArray();
                }
            } catch (\Throwable $e) {
                Log::warning("SWORD commentary read failed for {$moduleKey} {$book} {$chapter}: {$e->getMessage()}");
            }
        }

        return response()->json([
            'module' => $moduleKey,
            'book'   => $book,
            'chapter' => $chapter,
            'entries' => $entries,
        ]);
    }

    /**
     * Get a dictionary/lexicon entry by key.
     */
    public function dictionary(string $moduleKey, string $key): JsonResponse
    {
        $module = Module::where('key', $moduleKey)
            ->where('type', 'dictionary')
            ->where('is_installed', true)
            ->first();

        if (! $module) {
            return response()->json(['entry' => null, 'error' => 'Module not found'], 404);
        }

        // Try to read from SWORD binary
        if ($module->data_path) {
            try {
                $result = $this->swordManager->readDictionaryEntry($module, $key);
                if ($result['html'] !== null) {
                    return response()->json([
                        'module' => $moduleKey,
                        'key'    => $key,
                        'entry'  => $result['html'],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("SWORD dictionary read failed for {$moduleKey}/{$key}: {$e->getMessage()}");
            }
        }

        // Fallback: try DB (dictionary entries stored as verse_number = 0, text_raw = content)
        $verse = Verse::where('module_id', $module->id)
            ->where('book_osis_id', $key)
            ->first();

        if ($verse) {
            return response()->json([
                'module' => $moduleKey,
                'key'    => $key,
                'entry'  => $verse->text_rendered ?? $verse->text_raw,
            ]);
        }

        return response()->json([
            'module' => $moduleKey,
            'key'    => $key,
            'entry'  => null,
        ]);
    }

    /**
     * Get available dictionary keys (for autocomplete/search).
     */
    public function dictionaryKeys(string $moduleKey): JsonResponse
    {
        $module = Module::where('key', $moduleKey)
            ->where('type', 'dictionary')
            ->where('is_installed', true)
            ->first();

        if (! $module) {
            return response()->json(['keys' => []], 404);
        }

        try {
            $keys = $this->swordManager->getDictionaryKeys($module);
            return response()->json(['module' => $moduleKey, 'keys' => $keys]);
        } catch (\Throwable $e) {
            Log::warning("Failed to get dictionary keys for {$moduleKey}: {$e->getMessage()}");
            return response()->json(['module' => $moduleKey, 'keys' => []]);
        }
    }
}
