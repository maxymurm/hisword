<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Verse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends BaseApiController
{
    /**
     * Full-text search using Meilisearch (via Scout) with fallback to SQL LIKE.
     *
     * GET /api/v1/search?q={query}&module={key}&scope={all|ot|nt|book}&book={osis}&page=1&per_page=25
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'module' => ['sometimes', 'string', 'max:50'],
            'scope' => ['sometimes', 'string', 'in:all,ot,nt,book'],
            'book' => ['sometimes', 'string', 'max:20'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 25;
        $page = $validated['page'] ?? 1;

        // Use Meilisearch when driver is configured, otherwise fall back to SQL
        if (config('scout.driver') === 'meilisearch') {
            return $this->meilisearchQuery($validated, $perPage, $page);
        }

        return $this->sqlFallbackQuery($validated, $perPage);
    }

    /**
     * Search suggestions / autocomplete.
     *
     * GET /api/v1/search/suggest?q={partial}
     */
    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        if (config('scout.driver') === 'meilisearch') {
            $results = Verse::search($validated['q'])
                ->options([
                    'limit' => 8,
                    'attributesToRetrieve' => ['reference', 'book_name', 'chapter_number', 'verse_number', 'book_osis_id'],
                ])
                ->raw();

            $suggestions = collect($results['hits'] ?? [])->map(fn($hit) => [
                'reference' => $hit['reference'] ?? '',
                'book_name' => $hit['book_name'] ?? '',
                'book_osis_id' => $hit['book_osis_id'] ?? '',
                'chapter' => $hit['chapter_number'] ?? 0,
                'verse' => $hit['verse_number'] ?? 0,
            ]);

            return $this->success($suggestions);
        }

        // SQL fallback — return matching book/ref names
        $likeOp = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
        $suggestions = Verse::where('text_raw', $likeOp, '%' . $validated['q'] . '%')
            ->select(['book_osis_id', 'chapter_number', 'verse_number'])
            ->limit(8)
            ->get()
            ->map(fn($v) => [
                'reference' => $v->book_osis_id . ' ' . $v->chapter_number . ':' . $v->verse_number,
                'book_name' => config('bible.osis_to_name.' . $v->book_osis_id, $v->book_osis_id),
                'book_osis_id' => $v->book_osis_id,
                'chapter' => $v->chapter_number,
                'verse' => $v->verse_number,
            ]);

        return $this->success($suggestions);
    }

    // ── Private helpers ──────────────────────────────

    /**
     * Search using Meilisearch via Scout with highlight snippets.
     */
    private function meilisearchQuery(array $validated, int $perPage, int $page): JsonResponse
    {
        $filters = $this->buildMeilisearchFilters($validated);

        $raw = Verse::search($validated['q'])
            ->options(array_filter([
                'filter' => $filters ?: null,
                'limit' => $perPage,
                'offset' => ($page - 1) * $perPage,
                'attributesToHighlight' => ['text'],
                'highlightPreTag' => '<mark>',
                'highlightPostTag' => '</mark>',
                'attributesToRetrieve' => [
                    'id', 'text', 'book_osis_id', 'book_name',
                    'chapter_number', 'verse_number', 'module_id',
                    'module_key', 'testament', 'reference',
                ],
                'showMatchesPosition' => true,
            ]))
            ->raw();

        $hits = collect($raw['hits'] ?? [])->map(fn($hit) => [
            'id' => $hit['id'],
            'reference' => $hit['reference'] ?? '',
            'book_osis_id' => $hit['book_osis_id'] ?? '',
            'book_name' => $hit['book_name'] ?? '',
            'chapter_number' => $hit['chapter_number'] ?? 0,
            'verse_number' => $hit['verse_number'] ?? 0,
            'text' => $hit['text'] ?? '',
            'highlight' => $hit['_formatted']['text'] ?? $hit['text'] ?? '',
            'module_id' => $hit['module_id'] ?? null,
            'module_key' => $hit['module_key'] ?? '',
            'testament' => $hit['testament'] ?? '',
        ]);

        $estimatedTotal = $raw['estimatedTotalHits'] ?? $raw['totalHits'] ?? count($hits);

        return response()->json([
            'success' => true,
            'data' => $hits,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $estimatedTotal,
                'last_page' => (int) ceil($estimatedTotal / $perPage),
                'query' => $validated['q'],
                'processing_time_ms' => $raw['processingTimeMs'] ?? null,
            ],
        ]);
    }

    /**
     * Build Meilisearch filter string from request parameters.
     */
    private function buildMeilisearchFilters(array $validated): string
    {
        $filters = [];

        if (isset($validated['module'])) {
            $filters[] = 'module_key = "' . $validated['module'] . '"';
        }

        if (isset($validated['scope'])) {
            match ($validated['scope']) {
                'ot' => $filters[] = 'testament = "OT"',
                'nt' => $filters[] = 'testament = "NT"',
                'book' => isset($validated['book'])
                    ? $filters[] = 'book_osis_id = "' . $validated['book'] . '"'
                    : null,
                default => null,
            };
        }

        return implode(' AND ', $filters);
    }

    /**
     * Fallback SQL search (LIKE / ILIKE) when Meilisearch is unavailable.
     */
    private function sqlFallbackQuery(array $validated, int $perPage): JsonResponse
    {
        $query = Verse::query();

        // Filter by module
        if (isset($validated['module'])) {
            $query->whereHas('module', fn($q) => $q->where('key', $validated['module']));
        }

        // Filter by scope
        if (isset($validated['scope'])) {
            match ($validated['scope']) {
                'ot' => $query->whereIn('book_osis_id', config('bible.ot_books', [])),
                'nt' => $query->whereIn('book_osis_id', config('bible.nt_books', [])),
                'book' => isset($validated['book'])
                    ? $query->where('book_osis_id', $validated['book'])
                    : null,
                default => null,
            };
        }

        // Text search
        $likeOp = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
        $query->where('text_raw', $likeOp, '%' . $validated['q'] . '%');

        return $this->paginated(
            $query->select(['id', 'module_id', 'book_osis_id', 'chapter_number', 'verse_number', 'text_raw']),
            $perPage,
        );
    }
}
