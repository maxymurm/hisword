<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Highlight;
use App\Models\Module;
use App\Models\Note;
use App\Services\BibleReaderFactory;
use App\Services\CacheService;
use App\Services\Sword\SwordManager;
use App\Services\Sword\Versification\KjvVersification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReaderController extends Controller
{
    public function __construct(
        protected CacheService $cache,
        protected SwordManager $swordManager,
        protected BibleReaderFactory $readerFactory,
        protected KjvVersification $versification,
    ) {}

    public function show(Request $request, ?string $module = null, ?string $book = null, ?int $chapter = null): Response
    {
        // Resolve module
        $moduleKey = $module ?? $this->defaultModule($request);
        $moduleModel = Module::where('key', $moduleKey)
            ->where('type', 'bible')
            ->first();

        if (! $moduleModel) {
            $moduleModel = Module::where('type', 'bible')
                ->where('is_installed', true)
                ->first();
            $moduleKey = $moduleModel?->key ?? 'KJV';
        }

        // Available Bible modules (cached) — include engine for frontend
        $modules = $this->cache->installedModules('bible', fn () =>
            Module::where('type', 'bible')
                ->where('is_installed', true)
                ->select('id', 'key', 'name', 'language', 'description', 'engine')
                ->orderBy('name')
                ->get()
        );

        // Determine if binary data files are available (SWORD or Bintex)
        $hasBinaryData = $moduleModel && $this->readerFactory->hasDataFiles($moduleModel);

        // Books: derive from versification for binary path, DB fallback
        $books = $hasBinaryData
            ? $this->booksFromVersification()
            : ($moduleModel
                ? $this->cache->booksForModule($moduleModel->id, fn () =>
                    Book::where('module_id', $moduleModel->id)
                        ->select('id', 'osis_id', 'name', 'abbreviation', 'testament', 'book_order', 'chapter_count')
                        ->orderBy('book_order')
                        ->get()
                )
                : collect());

        // Resolve book
        $bookOsis = $book ?? 'Gen';
        $bookModel = $books->firstWhere('osis_id', $bookOsis);
        if (! $bookModel && $books->isNotEmpty()) {
            $bookModel = $books->first();
            $bookOsis = $bookModel->osis_id;
        }

        // Resolve chapter
        $chapterNumber = $chapter ?? 1;
        $totalChapters = $bookModel->chapter_count ?? 1;
        if ($chapterNumber < 1 || $chapterNumber > $totalChapters) {
            $chapterNumber = 1;
        }

        // Get verses — binary PRIMARY (SWORD or Bintex), DB fallback
        $verses = [];
        if ($hasBinaryData && $moduleModel) {
            $verses = $this->cache->verses($moduleKey, $bookOsis, $chapterNumber, function () use ($moduleModel, $bookOsis, $chapterNumber) {
                $rawVerses = $this->readerFactory->readChapter($moduleModel, $bookOsis, $chapterNumber);
                if ($rawVerses === null) {
                    return [];
                }
                return collect($rawVerses)->map(fn ($data, $verseNum) => [
                    'number'      => $verseNum,
                    'text'        => $data['html'] ?? $data['raw'] ?? '',
                    'strongs'     => $data['strongs_data'] ?? null,
                    'footnotes'   => null,
                    'cross_refs'  => null,
                ])->values()->toArray();
            });
        }

        // DB fallback: read from verses table if binary produced nothing
        if (empty($verses) && $moduleModel) {
            $dbVerses = \App\Models\Verse::where('module_id', $moduleModel->id)
                ->where('book_osis_id', $bookOsis)
                ->where('chapter_number', $chapterNumber)
                ->orderBy('verse_number')
                ->get();

            if ($dbVerses->isNotEmpty()) {
                $verses = $dbVerses->map(fn ($v) => [
                    'number'      => $v->verse_number,
                    'text'        => $v->text_rendered ?? $v->text_raw,
                    'strongs'     => $v->strongs_data,
                    'footnotes'   => $v->footnotes,
                    'cross_refs'  => $v->cross_refs,
                ])->toArray();
            }
        }

        // Prev/next links
        $prevLink = $this->adjacentChapter($books, $bookOsis, $chapterNumber, $moduleKey, -1);
        $nextLink = $this->adjacentChapter($books, $bookOsis, $chapterNumber, $moduleKey, 1);

        // User annotations for this chapter (if authenticated) – not cached (user-specific, mutable)
        $highlights = [];
        $notes = [];
        if ($request->user()) {
            $userId = $request->user()->id;

            $highlights = Highlight::where('user_id', $userId)
                ->where('book_osis_id', $bookOsis)
                ->where('chapter_number', $chapterNumber)
                ->where('is_deleted', false)
                ->when($moduleKey, fn ($q) => $q->where('module_key', $moduleKey))
                ->select('verse_number', 'color')
                ->get()
                ->map(fn ($h) => [
                    'verse'  => $h->verse_number,
                    'color'  => $h->color->value ?? $h->color,
                ])
                ->toArray();

            $notes = Note::where('user_id', $userId)
                ->where('book_osis_id', $bookOsis)
                ->where('chapter_number', $chapterNumber)
                ->where('is_deleted', false)
                ->select('id', 'verse_start', 'verse_end', 'title')
                ->get()
                ->toArray();
        }

        // Supplementary modules (cached)
        $commentaryModules = $this->cache->installedModules('commentary', fn () =>
            Module::where('type', 'commentary')
                ->where('is_installed', true)
                ->select('id', 'key', 'name')
                ->get()
        );

        $dictionaryModules = $this->cache->installedModules('dictionary', fn () =>
            Module::where('type', 'dictionary')
                ->where('is_installed', true)
                ->select('id', 'key', 'name')
                ->get()
        );

        return Inertia::render('Reader', [
            'moduleKey'          => $moduleKey,
            'modules'            => $modules,
            'books'              => $books,
            'currentBook'        => $bookModel ? [
                'osis_id'       => $bookModel->osis_id,
                'name'          => $bookModel->name,
                'abbreviation'  => $bookModel->abbreviation,
                'testament'     => $bookModel->testament,
                'chapter_count' => $bookModel->chapter_count,
            ] : null,
            'chapterNumber'      => $chapterNumber,
            'totalChapters'      => $totalChapters,
            'verses'             => $verses,
            'prevLink'           => $prevLink,
            'nextLink'           => $nextLink,
            'highlights'         => $highlights,
            'notes'              => $notes,
            'commentaryModules'  => $commentaryModules,
            'dictionaryModules'  => $dictionaryModules,
        ]);
    }

    /**
     * Return verses as JSON for parallel reading.
     */
    public function verses(string $module, string $book, int $chapter): \Illuminate\Http\JsonResponse
    {
        $moduleModel = Module::where('key', $module)
            ->where('type', 'bible')
            ->where('is_installed', true)
            ->first();

        if (!$moduleModel) {
            return response()->json(['verses' => []]);
        }

        // Binary PRIMARY (SWORD or Bintex)
        $verses = [];
        if ($this->readerFactory->hasDataFiles($moduleModel)) {
            $verses = $this->cache->verses($module, $book, $chapter, function () use ($moduleModel, $book, $chapter) {
                $rawVerses = $this->readerFactory->readChapter($moduleModel, $book, $chapter);
                if ($rawVerses === null) {
                    return [];
                }
                return collect($rawVerses)->map(fn ($data, $verseNum) => [
                    'number' => $verseNum,
                    'text'   => $data['html'] ?? $data['raw'] ?? '',
                ])->values()->toArray();
            });
        }

        // DB fallback
        if (empty($verses)) {
            $verses = \App\Models\Verse::where('module_id', $moduleModel->id)
                ->where('book_osis_id', $book)
                ->where('chapter_number', $chapter)
                ->orderBy('verse_number')
                ->get()
                ->map(fn ($v) => [
                    'number' => $v->verse_number,
                    'text'   => $v->text_rendered ?? $v->text_raw,
                ])
                ->toArray();
        }

        return response()->json(['verses' => $verses]);
    }

    private function defaultModule(Request $request): string
    {
        if ($request->user()) {
            $pref = \App\Models\UserPreference::where('user_id', $request->user()->id)
                ->where('key', 'default_bible_module')
                ->first();
            if ($pref) {
                return is_array($pref->value) ? ($pref->value['module'] ?? 'KJV') : $pref->value;
            }
        }

        return 'KJV';
    }

    private function adjacentChapter($books, string $currentBook, int $currentChapter, string $moduleKey, int $direction): ?array
    {
        $bookIndex = $books->search(fn ($b) => $b->osis_id === $currentBook);
        if ($bookIndex === false) {
            return null;
        }

        $book = $books[$bookIndex];
        $newChapter = $currentChapter + $direction;

        if ($newChapter >= 1 && $newChapter <= $book->chapter_count) {
            return [
                'url'   => "/read/{$moduleKey}/{$currentBook}/{$newChapter}",
                'label' => "{$book->abbreviation} {$newChapter}",
            ];
        }

        // Move to adjacent book
        $newBookIndex = $bookIndex + $direction;
        if ($newBookIndex >= 0 && $newBookIndex < $books->count()) {
            $newBook = $books[$newBookIndex];
            $ch = $direction > 0 ? 1 : $newBook->chapter_count;
            return [
                'url'   => "/read/{$moduleKey}/{$newBook->osis_id}/{$ch}",
                'label' => "{$newBook->abbreviation} {$ch}",
            ];
        }

        return null;
    }

    /**
     * Build a books collection from KJV versification data.
     * Returns stdClass objects matching the shape of Eloquent Book models.
     */
    private function booksFromVersification(): \Illuminate\Support\Collection
    {
        return collect($this->versification->getAllBooks())->map(fn (string $osisId) => (object) [
            'osis_id'       => $osisId,
            'name'          => $this->versification->getBookName($osisId),
            'abbreviation'  => $osisId,
            'testament'     => strtoupper($this->versification->getTestament($osisId)),
            'book_order'    => $this->versification->getBookOrder($osisId),
            'chapter_count' => $this->versification->getChapterCount($osisId),
        ]);
    }
}
