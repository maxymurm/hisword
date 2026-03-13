<?php

namespace Tests\Unit\Services\Sword;

use App\Models\Module;
use App\Services\Sword\SwordManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwordManagerTest extends TestCase
{
    use RefreshDatabase;

    private SwordManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(SwordManager::class);
        $this->ensureModulesInstalled();
    }

    private function ensureModulesInstalled(): void
    {
        if (!Module::where('key', 'KJV')->where('is_installed', true)->exists()) {
            $this->artisan('sword:install-bundled');
        }
    }

    private function kjv(): Module
    {
        return Module::where('key', 'KJV')->where('is_installed', true)->firstOrFail();
    }

    public function test_read_chapter_returns_31_verses_for_genesis_1(): void
    {
        $verses = $this->manager->readChapter($this->kjv(), 'Gen', 1);
        $this->assertCount(31, $verses);
    }

    public function test_read_chapter_genesis_1_1_contains_in_the_beginning(): void
    {
        $verses = $this->manager->readChapter($this->kjv(), 'Gen', 1);
        $this->assertStringContainsString('In the beginning', $verses[1]['plain']);
    }

    public function test_read_chapter_returns_html_with_strongs(): void
    {
        $verses = $this->manager->readChapter($this->kjv(), 'Gen', 1);
        $this->assertStringContainsString('data-strongs=', $verses[1]['html']);
    }

    public function test_read_chapter_returns_strongs_data(): void
    {
        $verses = $this->manager->readChapter($this->kjv(), 'Gen', 1);
        $this->assertNotEmpty($verses[1]['strongs_data']);
        $first = $verses[1]['strongs_data'][0];
        $this->assertArrayHasKey('word', $first);
        $this->assertArrayHasKey('strongs', $first);
        $this->assertArrayHasKey('morph', $first);
    }

    public function test_read_verse_genesis_1_1(): void
    {
        $result = $this->manager->readVerse($this->kjv(), 'Gen', 1, 1);
        $this->assertNotNull($result['raw']);
        $this->assertStringContainsString('beginning', $result['plain']);
    }

    public function test_read_chapter_john_3_16_has_red_letters(): void
    {
        $verses = $this->manager->readChapter($this->kjv(), 'John', 3);
        $this->assertStringContainsString('red-letter', $verses[16]['html']);
    }

    public function test_read_chapter_psalm_23_1_has_divine_name(): void
    {
        $verses = $this->manager->readChapter($this->kjv(), 'Ps', 23);
        $this->assertStringContainsString('divine-name', $verses[1]['html']);
    }

    public function test_read_chapter_performance_under_200ms(): void
    {
        $start = microtime(true);
        $this->manager->readChapter($this->kjv(), 'Gen', 1);
        $elapsed = (microtime(true) - $start) * 1000;
        $this->assertLessThan(200, $elapsed, "Genesis 1 read took {$elapsed}ms (should be < 200ms)");
    }

    public function test_has_data_files(): void
    {
        $this->assertTrue($this->manager->hasDataFiles($this->kjv()));
    }

    public function test_get_text_reader(): void
    {
        $reader = $this->manager->getTextReader($this->kjv());
        $this->assertEquals('zText', $reader->getDriverName());
    }

    public function test_get_filter(): void
    {
        $filter = $this->manager->getFilter($this->kjv());
        $this->assertEquals('OSIS', $filter->getName());
    }

    public function test_read_dictionary_entry(): void
    {
        $mod = Module::where('key', 'StrongsRealGreek')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('StrongsRealGreek not installed');
        }

        $result = $this->manager->readDictionaryEntry($mod, 'G2316');
        $this->assertNotNull($result['html']);
        $this->assertStringContainsString('deity', $result['raw']);
    }

    public function test_get_dictionary_keys(): void
    {
        $mod = Module::where('key', 'StrongsRealGreek')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('StrongsRealGreek not installed');
        }

        $keys = $this->manager->getDictionaryKeys($mod);
        $this->assertNotEmpty($keys);
        // Keys are stored as zero-padded numbers without G/H prefix
        $this->assertContains('02316', $keys);
    }

    public function test_read_commentary(): void
    {
        $mod = Module::where('key', 'MHCC')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('MHCC not installed');
        }

        $verses = $this->manager->readChapter($mod, 'Gen', 1);
        $this->assertNotEmpty($verses);
    }
}
