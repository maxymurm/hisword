<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Verse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrossReferenceController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('CrossReferences', [
            'book' => $request->query('book', 'Gen'),
            'chapter' => (int) $request->query('chapter', 1),
            'verse' => $request->query('verse') ? (int) $request->query('verse') : null,
            'module' => $request->query('module', 'KJV'),
        ]);
    }

    public function lookup(Request $request): JsonResponse
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
            return response()->json(['cross_references' => [], 'footnotes' => []]);
        }

        $crossRefs = collect($verse->cross_refs ?? [])->map(function ($ref) use ($verse) {
            $targetVerse = null;
            $book = $ref['book'] ?? null;
            $chapter = $ref['chapter'] ?? null;
            $verseNum = $ref['verse_start'] ?? $ref['verse'] ?? null;

            if ($book && $chapter && $verseNum) {
                $targetVerse = Verse::where('module_id', $verse->module_id)
                    ->where('book_osis_id', $book)
                    ->where('chapter_number', $chapter)
                    ->where('verse_number', $verseNum)
                    ->first();
            }

            return [
                'reference' => $ref['reference'] ?? "{$book} {$chapter}:{$verseNum}",
                'book' => $book,
                'chapter' => $chapter,
                'verse' => $verseNum,
                'text' => $targetVerse?->text_rendered ?? $targetVerse?->text_raw ?? null,
                'type' => $ref['type'] ?? 'cross-reference',
            ];
        })->toArray();

        return response()->json([
            'cross_references' => $crossRefs,
            'footnotes' => $verse->footnotes ?? [],
        ]);
    }
}
