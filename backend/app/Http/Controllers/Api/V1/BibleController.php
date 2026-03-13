<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Module;
use App\Models\Verse;
use App\Services\BibleReaderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BibleController extends BaseApiController
{
    public function __construct(
        private BibleReaderFactory $readerFactory,
    ) {}

    /**
     * Read a chapter from any module engine (SWORD or Bintex).
     *
     * GET /api/v1/read/{moduleKey}/{book}/{chapter}?verse={verse}
     */
    public function read(Request $request, string $moduleKey, string $book, int $chapter): JsonResponse
    {
        $module = Module::where('key', $moduleKey)->firstOrFail();
        $verse = $request->integer('verse');

        if ($verse > 0) {
            $data = $this->readerFactory->readVerse($module, $book, $chapter, $verse);

            return $this->success([
                'module' => $moduleKey,
                'book' => $book,
                'chapter' => $chapter,
                'verse' => $verse,
                'text' => $data,
            ]);
        }

        $verses = $this->readerFactory->readChapter($module, $book, $chapter);

        return $this->success([
            'module' => $moduleKey,
            'book' => $book,
            'chapter' => $chapter,
            'verse_count' => count($verses),
            'verses' => $verses,
        ]);
    }

    /**
     * List books for a module.
     */
    public function books(string $module): JsonResponse
    {
        $mod = Module::where('key', $module)->orWhere('id', $module)->firstOrFail();

        $books = $mod->books()
            ->orderBy('book_order')
            ->get(['id', 'osis_id', 'name', 'abbreviation', 'testament', 'book_order', 'chapter_count']);

        return $this->success($books);
    }

    /**
     * List chapters for a book.
     */
    public function chapters(int $book): JsonResponse
    {
        $bookModel = Book::findOrFail($book);

        $chapters = $bookModel->chapters()
            ->orderBy('chapter_number')
            ->get(['id', 'chapter_number', 'verse_count']);

        return $this->success($chapters);
    }

    /**
     * List verses for a chapter.
     */
    public function verses(int $chapter): JsonResponse
    {
        $chapterModel = Chapter::findOrFail($chapter);

        $verses = Verse::where('module_id', $chapterModel->book->module_id)
            ->where('book_osis_id', $chapterModel->book->osis_id)
            ->where('chapter_number', $chapterModel->chapter_number)
            ->orderBy('verse_number')
            ->get(['id', 'verse_number', 'text_raw', 'text_rendered', 'strongs_data', 'footnotes', 'cross_refs']);

        return $this->success($verses);
    }

    /**
     * Show a single verse.
     */
    public function showVerse(int $verse): JsonResponse
    {
        $v = Verse::findOrFail($verse);

        return $this->success($v);
    }
}
