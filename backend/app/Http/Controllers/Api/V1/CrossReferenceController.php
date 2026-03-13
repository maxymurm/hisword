<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Verse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrossReferenceController extends BaseApiController
{
    /**
     * Look up cross-references for a specific verse.
     *
     * GET /cross-references?book=Gen&chapter=1&verse=1&module=KJV
     */
    public function forVerse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book' => ['required', 'string'],
            'chapter' => ['required', 'integer'],
            'verse' => ['required', 'integer'],
            'module' => ['sometimes', 'string'],
        ]);

        $query = Verse::where('book_osis_id', $validated['book'])
            ->where('chapter_number', $validated['chapter'])
            ->where('verse_number', $validated['verse']);

        if (!empty($validated['module'])) {
            $query->whereHas('module', fn ($q) => $q->where('key', $validated['module']));
        }

        $verse = $query->first();

        if (!$verse) {
            return $this->error('Verse not found', 404);
        }

        $crossRefs = $verse->cross_refs ?? [];
        $footnotes = $verse->footnotes ?? [];

        // Resolve cross-reference targets to actual verse text when available
        $resolvedRefs = collect($crossRefs)->map(function ($ref) use ($verse) {
            $resolved = [
                'book' => $ref['book'] ?? null,
                'chapter' => $ref['chapter'] ?? null,
                'verse_start' => $ref['verse_start'] ?? $ref['verse'] ?? null,
                'verse_end' => $ref['verse_end'] ?? null,
                'reference' => $ref['reference'] ?? $this->formatReference($ref),
                'type' => $ref['type'] ?? 'cross-reference',
            ];

            // Try to look up the referenced verse text
            if ($resolved['book'] && $resolved['chapter'] && $resolved['verse_start']) {
                $targetVerse = Verse::where('module_id', $verse->module_id)
                    ->where('book_osis_id', $resolved['book'])
                    ->where('chapter_number', $resolved['chapter'])
                    ->where('verse_number', $resolved['verse_start'])
                    ->first();

                if ($targetVerse) {
                    $resolved['text'] = $targetVerse->text_rendered ?? $targetVerse->text_raw;
                }
            }

            return $resolved;
        })->toArray();

        return $this->success([
            'verse' => [
                'book' => $verse->book_osis_id,
                'chapter' => $verse->chapter_number,
                'verse' => $verse->verse_number,
            ],
            'cross_references' => $resolvedRefs,
            'footnotes' => $footnotes,
        ]);
    }

    /**
     * Get all cross-references for a chapter.
     *
     * GET /cross-references/chapter?book=Gen&chapter=1&module=KJV
     */
    public function forChapter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book' => ['required', 'string'],
            'chapter' => ['required', 'integer'],
            'module' => ['sometimes', 'string'],
        ]);

        $query = Verse::where('book_osis_id', $validated['book'])
            ->where('chapter_number', $validated['chapter'])
            ->where(function ($q) {
                $q->whereNotNull('cross_refs')
                    ->orWhereNotNull('footnotes');
            });

        if (!empty($validated['module'])) {
            $query->whereHas('module', fn ($q) => $q->where('key', $validated['module']));
        }

        $verses = $query->orderBy('verse_number')->get();

        $result = $verses->map(function (Verse $verse) {
            return [
                'verse_number' => $verse->verse_number,
                'cross_references' => $verse->cross_refs ?? [],
                'footnotes' => $verse->footnotes ?? [],
            ];
        })->filter(function ($item) {
            return !empty($item['cross_references']) || !empty($item['footnotes']);
        })->values()->toArray();

        return $this->success([
            'book' => $validated['book'],
            'chapter' => $validated['chapter'],
            'verses' => $result,
            'total_cross_refs' => collect($result)->sum(fn ($v) => count($v['cross_references'])),
            'total_footnotes' => collect($result)->sum(fn ($v) => count($v['footnotes'])),
        ]);
    }

    /**
     * Format a reference from parsed data.
     */
    private function formatReference(array $ref): string
    {
        $bookNames = config('bible.osis_to_name', []);
        $book = $bookNames[$ref['book'] ?? ''] ?? $ref['book'] ?? '';
        $chapter = $ref['chapter'] ?? '';
        $verse = $ref['verse_start'] ?? $ref['verse'] ?? '';
        $end = $ref['verse_end'] ?? null;

        $result = trim("{$book} {$chapter}:{$verse}");
        if ($end) {
            $result .= "-{$end}";
        }

        return $result;
    }
}
