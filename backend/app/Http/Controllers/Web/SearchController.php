<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Verse;
use App\Services\CacheService;
use App\Services\Sword\SwordManager;
use App\Services\Sword\SwordSearcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(
        protected CacheService $cache,
        protected SwordSearcher $searcher,
    ) {}

    /**
     * Render the search page with initial results if `q` is present.
     */
    public function index(Request $request): Response
    {
        $modules = $this->cache->installedModules('bible_search', fn () =>
            Module::where('is_installed', true)
                ->where('type', 'bible')
                ->orderBy('name')
                ->get(['id', 'key', 'name', 'language'])
        );

        $books = collect(config('bible.osis_to_name', []))
            ->map(fn($name, $osis) => [
                'osis_id' => $osis,
                'name' => $name,
                'testament' => in_array($osis, config('bible.ot_books', [])) ? 'OT' : 'NT',
            ])
            ->values();

        $query = $request->query('q', '');
        $results = null;

        if ($query && strlen($query) >= 2) {
            $results = $this->performSearch(
                $query,
                $request->query('module'),
                $request->query('scope', 'all'),
                $request->query('book'),
                (int) $request->query('page', 1),
                25
            );
        }

        return Inertia::render('Search', [
            'modules' => $modules,
            'books' => $books,
            'initialQuery' => $query,
            'initialResults' => $results,
        ]);
    }

    /**
     * AJAX search endpoint for real-time results.
     */
    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'module' => ['sometimes', 'nullable', 'string', 'max:50'],
            'scope' => ['sometimes', 'string', 'in:all,ot,nt,book'],
            'book' => ['sometimes', 'nullable', 'string', 'max:20'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $results = $this->performSearch(
            $validated['q'],
            $validated['module'] ?? null,
            $validated['scope'] ?? 'all',
            $validated['book'] ?? null,
            $validated['page'] ?? 1,
            25
        );

        return response()->json($results);
    }

    /**
     * Shared search logic – uses SWORD FTS5 index when available, DB fallback.
     */
    private function performSearch(
        string $q,
        ?string $module,
        string $scope,
        ?string $book,
        int $page,
        int $perPage
    ): array {
        // Try SWORD FTS5 search first
        if ($module) {
            $moduleModel = Module::where('key', $module)->where('is_installed', true)->first();
            if ($moduleModel && $this->searcher->hasIndex($moduleModel)) {
                return $this->performFtsSearch($moduleModel, $q, $scope, $book, $page, $perPage);
            }
        }

        // DB fallback
        return $this->performDbSearch($q, $module, $scope, $book, $page, $perPage);
    }

    /**
     * Search using SWORD FTS5 index.
     */
    private function performFtsSearch(
        Module $moduleModel,
        string $q,
        string $scope,
        ?string $book,
        int $page,
        int $perPage,
    ): array {
        $result = $this->searcher->search($moduleModel, $q, $perPage, ($page - 1) * $perPage);

        // Apply scope/book filters post-query (FTS5 searches all verses)
        $otBooks = config('bible.ot_books', []);
        $hits = collect($result['hits'])
            ->when($scope === 'ot', fn ($c) => $c->filter(fn ($h) => in_array($h['book_osis_id'], $otBooks)))
            ->when($scope === 'nt', fn ($c) => $c->filter(fn ($h) => !in_array($h['book_osis_id'], $otBooks)))
            ->when($scope === 'book' && $book, fn ($c) => $c->filter(fn ($h) => $h['book_osis_id'] === $book))
            ->map(fn ($h) => array_merge($h, [
                'module_key'  => $moduleModel->key,
                'module_name' => $moduleModel->name,
                'text'        => strip_tags($h['highlight']),
            ]))
            ->values();

        $total = $result['total'];
        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'hits' => $hits,
            'meta' => [
                'query'        => $q,
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
            ],
        ];
    }

    /**
     * Search using database LIKE query (original implementation).
     */
    private function performDbSearch(
        string $q,
        ?string $module,
        string $scope,
        ?string $book,
        int $page,
        int $perPage,
    ): array {
        $query = Verse::query()->with('module:id,key,name');

        // Module filter – resolve to module_id directly (avoids correlated subquery)
        if ($module) {
            $moduleModel = Module::where('key', $module)->first();
            if ($moduleModel) {
                $query->where('module_id', $moduleModel->id);
            }
        }

        // Scope filter
        match ($scope) {
            'ot' => $query->whereIn('book_osis_id', config('bible.ot_books', [])),
            'nt' => $query->whereIn('book_osis_id', config('bible.nt_books', [])),
            'book' => $book ? $query->where('book_osis_id', $book) : null,
            default => null,
        };

        // Text search
        $likeOp = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
        $query->where('text_raw', $likeOp, '%' . $q . '%');

        // Use a single query with SQL_CALC_FOUND_ROWS equivalent
        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $verses = $query
            ->orderBy('book_osis_id')
            ->orderBy('chapter_number')
            ->orderBy('verse_number')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $bookNames = config('bible.osis_to_name', []);

        $hits = $verses->map(fn($v) => [
            'id' => $v->id,
            'reference' => ($bookNames[$v->book_osis_id] ?? $v->book_osis_id) . ' ' . $v->chapter_number . ':' . $v->verse_number,
            'book_osis_id' => $v->book_osis_id,
            'book_name' => $bookNames[$v->book_osis_id] ?? $v->book_osis_id,
            'chapter_number' => $v->chapter_number,
            'verse_number' => $v->verse_number,
            'text' => $v->text_raw,
            'highlight' => $this->highlightText($v->text_raw, $q),
            'module_key' => $v->module?->key ?? '',
            'module_name' => $v->module?->name ?? '',
        ]);

        return [
            'hits' => $hits,
            'meta' => [
                'query' => $q,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Highlight matching text with <mark> tags.
     */
    private function highlightText(string $text, string $query): string
    {
        $escaped = preg_quote($query, '/');
        return preg_replace(
            '/(' . $escaped . ')/i',
            '<mark>$1</mark>',
            e($text)
        ) ?? e($text);
    }
}
