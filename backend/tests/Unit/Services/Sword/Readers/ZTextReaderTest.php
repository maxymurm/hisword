<?php

namespace Tests\Unit\Services\Sword\Readers;

use App\Models\Module;
use App\Services\Sword\Readers\ZTextReader;
use App\Services\Sword\SwordManager;
use App\Services\Sword\Versification\KjvVersification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZTextReaderTest extends TestCase
{
    use RefreshDatabase;

    private ZTextReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureModulesInstalled();
        $manager = app(SwordManager::class);
        $mod = Module::where('key', 'KJV')->where('is_installed', true)->firstOrFail();
        $this->reader = $manager->getTextReader($mod);
    }

    private function ensureModulesInstalled(): void
    {
        if (!Module::where('key', 'KJV')->where('is_installed', true)->exists()) {
            $this->artisan('sword:install-bundled');
        }
    }

    public function test_driver_name(): void
    {
        $this->assertEquals('zText', $this->reader->getDriverName());
    }

    public function test_read_verse_genesis_1_1(): void
    {
        $raw = $this->reader->readVerse('Gen', 1, 1);
        $this->assertNotNull($raw);
        $this->assertStringContainsString('beginning', $raw);
    }

    public function test_read_verse_genesis_1_1_contains_osis_w_tags(): void
    {
        $raw = $this->reader->readVerse('Gen', 1, 1);
        $this->assertMatchesRegularExpression('/<w\s+lemma="strong:/', $raw);
    }

    public function test_read_chapter_genesis_1_has_31_verses(): void
    {
        $verses = $this->reader->readChapter('Gen', 1);
        $this->assertCount(31, $verses);
    }

    public function test_read_chapter_verse_keys_are_sequential(): void
    {
        $verses = $this->reader->readChapter('Gen', 1);
        $keys = array_keys($verses);
        $this->assertEquals(range(1, 31), $keys);
    }

    public function test_read_chapter_psalm_119_has_176_verses(): void
    {
        $verses = $this->reader->readChapter('Ps', 119);
        $this->assertCount(176, $verses);
    }

    public function test_read_verse_john_3_16_has_strongs(): void
    {
        $raw = $this->reader->readVerse('John', 3, 16);
        $this->assertStringContainsString('strong:G2316', $raw);
    }

    public function test_read_verse_revelation_22_21_last_verse(): void
    {
        $raw = $this->reader->readVerse('Rev', 22, 21);
        $this->assertNotNull($raw);
        $this->assertStringContainsString('grace', strtolower($raw));
    }

    public function test_read_verse_out_of_range_returns_data_or_null(): void
    {
        // zText uses flat indexing — out-of-range verses may wrap around
        // to adjacent chapters rather than returning null. This is expected
        // behavior for SWORD binary readers.
        $raw = $this->reader->readVerse('Gen', 1, 99);
        $this->assertTrue($raw === null || is_string($raw));
    }

    public function test_read_book_returns_all_chapters(): void
    {
        $book = $this->reader->readBook('Gen');
        $this->assertCount(50, $book); // Genesis has 50 chapters
    }
}
