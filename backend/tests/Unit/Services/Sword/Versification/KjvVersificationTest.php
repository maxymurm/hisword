<?php

namespace Tests\Unit\Services\Sword\Versification;

use App\Services\Sword\Versification\KjvVersification;
use PHPUnit\Framework\TestCase;

class KjvVersificationTest extends TestCase
{
    private KjvVersification $v;

    protected function setUp(): void
    {
        $this->v = new KjvVersification();
    }

    public function test_flat_index_genesis_1_1_is_zero(): void
    {
        // SWORD reserves 2 slots at testament start + 1 for chapter-0 intro
        $this->assertEquals(4, $this->v->flatIndex('Gen', 1, 1));
    }

    public function test_flat_index_genesis_1_31(): void
    {
        $this->assertEquals(34, $this->v->flatIndex('Gen', 1, 31));
    }

    public function test_flat_index_genesis_2_1(): void
    {
        $this->assertEquals(36, $this->v->flatIndex('Gen', 2, 1));
    }

    public function test_get_verse_count_genesis_1(): void
    {
        $this->assertEquals(31, $this->v->getVerseCount('Gen', 1));
    }

    public function test_get_verse_count_psalm_119(): void
    {
        $this->assertEquals(176, $this->v->getVerseCount('Ps', 119));
    }

    public function test_get_testament_genesis_is_ot(): void
    {
        $this->assertEquals('ot', $this->v->getTestament('Gen'));
    }

    public function test_get_testament_matthew_is_nt(): void
    {
        $this->assertEquals('nt', $this->v->getTestament('Matt'));
    }

    public function test_get_chapter_count_genesis(): void
    {
        $this->assertEquals(50, $this->v->getChapterCount('Gen'));
    }

    public function test_get_chapter_count_revelation(): void
    {
        $this->assertEquals(22, $this->v->getChapterCount('Rev'));
    }

    public function test_get_all_books_count(): void
    {
        $books = $this->v->getAllBooks();
        $this->assertCount(66, $books);
    }

    public function test_first_book_is_genesis(): void
    {
        $books = $this->v->getAllBooks();
        $this->assertEquals('Gen', $books[0]);
    }

    public function test_last_book_is_revelation(): void
    {
        $books = $this->v->getAllBooks();
        $this->assertEquals('Rev', end($books));
    }

    public function test_get_book_name(): void
    {
        $this->assertEquals('Genesis', $this->v->getBookName('Gen'));
        $this->assertEquals('Revelation', $this->v->getBookName('Rev'));
    }

    public function test_get_book_order(): void
    {
        $this->assertEquals(1, $this->v->getBookOrder('Gen'));
        $this->assertEquals(66, $this->v->getBookOrder('Rev'));
    }

    public function test_get_book_index(): void
    {
        $this->assertEquals(0, $this->v->getBookIndex('Gen'));
    }

    public function test_get_total_verses(): void
    {
        $total = $this->v->getTotalVerses('Gen');
        $this->assertGreaterThan(0, $total);
        // Genesis has 1533 verses in KJV
        $this->assertEquals(1533, $total);
    }

    public function test_get_books_for_testament(): void
    {
        $otBooks = $this->v->getBooksForTestament('ot');
        $this->assertCount(39, $otBooks);
        $this->assertEquals('Gen', $otBooks[0]);

        $ntBooks = $this->v->getBooksForTestament('nt');
        $this->assertCount(27, $ntBooks);
        $this->assertEquals('Matt', $ntBooks[0]);
    }

    public function test_book_slot_count(): void
    {
        $slotCount = $this->v->bookSlotCount('Gen');
        $this->assertGreaterThan(0, $slotCount);
        // Should be >= total verses in Genesis
        $this->assertGreaterThanOrEqual(1533, $slotCount);
    }
}
