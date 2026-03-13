<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Module;
use App\Services\BibleReaderFactory;
use App\Services\Bintex\BintexManager;
use App\Services\Sword\SwordManager;
use PHPUnit\Framework\TestCase;

class BibleReaderFactoryTest extends TestCase
{
    private BibleReaderFactory $factory;
    private SwordManager $swordManager;
    private BintexManager $bintexManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->swordManager = $this->createMock(SwordManager::class);
        $this->bintexManager = $this->createMock(BintexManager::class);
        $this->factory = new BibleReaderFactory($this->swordManager, $this->bintexManager);
    }

    // ── Engine dispatch ─────────────────────────────

    public function test_read_verse_dispatches_to_sword_for_sword_engine(): void
    {
        $module = $this->makeModule('sword');

        $this->swordManager->expects($this->once())
            ->method('readVerse')
            ->with($module, 'Gen', 1, 1)
            ->willReturn(['raw' => 'In the beginning', 'html' => 'In the beginning', 'plain' => 'In the beginning']);

        $result = $this->factory->readVerse($module, 'Gen', 1, 1);

        $this->assertSame('In the beginning', $result['raw']);
    }

    public function test_read_verse_dispatches_to_bintex_for_bintex_engine(): void
    {
        $module = $this->makeModule('bintex', '/path/to/module.yes2');

        $this->bintexManager->expects($this->once())
            ->method('readVerse')
            ->with('/path/to/module.yes2', 1, 1, 1)
            ->willReturn('Pada mulanya');

        $result = $this->factory->readVerse($module, 'Gen', 1, 1);

        $this->assertSame('Pada mulanya', $result['raw']);
        $this->assertSame('Pada mulanya', $result['plain']);
    }

    public function test_read_verse_returns_nulls_when_bintex_returns_null(): void
    {
        $module = $this->makeModule('bintex', '/path/to/module.yes2');

        $this->bintexManager->expects($this->once())
            ->method('readVerse')
            ->willReturn(null);

        $result = $this->factory->readVerse($module, 'Gen', 1, 999);

        $this->assertNull($result['raw']);
        $this->assertNull($result['html']);
        $this->assertNull($result['plain']);
    }

    public function test_read_chapter_dispatches_to_sword(): void
    {
        $module = $this->makeModule('sword');

        $this->swordManager->expects($this->once())
            ->method('readChapter')
            ->with($module, 'John', 3)
            ->willReturn([
                1 => ['raw' => 'v1', 'html' => 'v1', 'plain' => 'v1', 'strongs_data' => []],
                2 => ['raw' => 'v2', 'html' => 'v2', 'plain' => 'v2', 'strongs_data' => []],
            ]);

        $result = $this->factory->readChapter($module, 'John', 3);

        $this->assertCount(2, $result);
        $this->assertSame('v1', $result[1]['raw']);
    }

    public function test_read_chapter_dispatches_to_bintex(): void
    {
        $module = $this->makeModule('bintex', '/path/to/module.yes');

        $this->bintexManager->expects($this->once())
            ->method('readChapter')
            ->with('/path/to/module.yes', 43, 3)  // John = bookId 43
            ->willReturn([1 => 'ayat 1', 2 => 'ayat 2']);

        $result = $this->factory->readChapter($module, 'John', 3);

        $this->assertCount(2, $result);
        $this->assertSame('ayat 1', $result[1]['raw']);
        $this->assertSame('ayat 1', $result[1]['plain']);
    }

    public function test_read_chapter_bintex_empty_returns_empty(): void
    {
        $module = $this->makeModule('bintex', '/path/to/module.yes');

        $this->bintexManager->expects($this->once())
            ->method('readChapter')
            ->willReturn([]);

        $result = $this->factory->readChapter($module, 'Gen', 1);

        $this->assertCount(0, $result);
    }

    public function test_default_engine_is_sword_when_null(): void
    {
        $module = $this->makeModule(null);

        $this->swordManager->expects($this->once())
            ->method('readVerse')
            ->willReturn(['raw' => 'text', 'html' => 'text', 'plain' => 'text']);

        $this->factory->readVerse($module, 'Gen', 1, 1);
    }

    // ── OSIS book mapping ───────────────────────────

    public function test_osis_mapping_genesis(): void
    {
        $module = $this->makeModule('bintex', '/m.yes');

        $this->bintexManager->expects($this->once())
            ->method('readVerse')
            ->with('/m.yes', 1, 1, 1)
            ->willReturn('text');

        $this->factory->readVerse($module, 'Gen', 1, 1);
    }

    public function test_osis_mapping_revelation(): void
    {
        $module = $this->makeModule('bintex', '/m.yes');

        $this->bintexManager->expects($this->once())
            ->method('readVerse')
            ->with('/m.yes', 66, 1, 1)
            ->willReturn('text');

        $this->factory->readVerse($module, 'Rev', 1, 1);
    }

    public function test_osis_mapping_psalms(): void
    {
        $module = $this->makeModule('bintex', '/m.yes');

        $this->bintexManager->expects($this->once())
            ->method('readVerse')
            ->with('/m.yes', 19, 23, 1)
            ->willReturn('The Lord is my shepherd');

        $this->factory->readVerse($module, 'Ps', 23, 1);
    }

    public function test_unknown_osis_id_throws_exception(): void
    {
        $module = $this->makeModule('bintex', '/m.yes');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown OSIS book ID: FakeBook');

        $this->factory->readVerse($module, 'FakeBook', 1, 1);
    }

    // ── hasDataFiles ────────────────────────────────

    public function test_has_data_files_dispatches_to_sword(): void
    {
        $module = $this->makeModule('sword');

        $this->swordManager->expects($this->once())
            ->method('hasDataFiles')
            ->with($module)
            ->willReturn(true);

        $this->assertTrue($this->factory->hasDataFiles($module));
    }

    public function test_has_data_files_bintex_with_null_path(): void
    {
        $module = $this->makeModule('bintex', null);

        $this->assertFalse($this->factory->hasDataFiles($module));
    }

    // ── Bintex HTML escaping ────────────────────────

    public function test_bintex_verse_html_is_escaped(): void
    {
        $module = $this->makeModule('bintex', '/m.yes');

        $this->bintexManager->expects($this->once())
            ->method('readVerse')
            ->willReturn('Text with <b>markup</b> & "quotes"');

        $result = $this->factory->readVerse($module, 'Gen', 1, 1);

        $this->assertSame('Text with <b>markup</b> & "quotes"', $result['raw']);
        $this->assertStringContainsString('&lt;b&gt;', $result['html']);
        $this->assertStringContainsString('&amp;', $result['html']);
    }

    // ── Helper ──────────────────────────────────────

    private function makeModule(?string $engine, ?string $dataPath = null): Module
    {
        $module = new Module();
        $module->engine = $engine;
        $module->data_path = $dataPath;
        $module->key = 'TEST';
        $module->name = 'Test Module';
        return $module;
    }
}
