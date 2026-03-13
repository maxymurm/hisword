<?php

namespace Tests\Unit\Services\Sword\Readers;

use App\Models\Module;
use App\Services\Sword\SwordManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZComReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Module::where('key', 'MHCC')->where('is_installed', true)->exists()) {
            $this->artisan('sword:install-bundled');
        }
    }

    public function test_mhcc_genesis_1_is_not_empty(): void
    {
        $mod = Module::where('key', 'MHCC')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('MHCC not installed');
        }

        $manager = app(SwordManager::class);
        $reader = $manager->getTextReader($mod);
        $this->assertStringContainsString('Com', $reader->getDriverName());

        $verses = $reader->readChapter('Gen', 1);
        $this->assertNotEmpty($verses);
    }

    public function test_mhcc_psalm_23_has_content(): void
    {
        $mod = Module::where('key', 'MHCC')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('MHCC not installed');
        }

        $manager = app(SwordManager::class);
        $verses = $manager->readChapter($mod, 'Ps', 23);
        $this->assertNotEmpty($verses);
    }
}
