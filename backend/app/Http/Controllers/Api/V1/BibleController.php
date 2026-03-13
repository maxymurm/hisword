<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Module;
use App\Models\Verse;
use Illuminate\Http\JsonResponse;

class BibleController extends BaseApiController
{
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
