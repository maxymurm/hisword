<?php

namespace Tests\Unit\Services\Sword\Readers;

use App\Models\Module;
use App\Services\Sword\SwordManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawLDReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Module::where('key', 'StrongsRealGreek')->where('is_installed', true)->exists()) {
            $this->artisan('sword:install-bundled');
        }
    }

    public function test_strongs_greek_g2316_contains_theos(): void
    {
        $mod = Module::where('key', 'StrongsRealGreek')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('StrongsRealGreek not installed');
        }

        $manager = app(SwordManager::class);
        $result = $manager->readDictionaryEntry($mod, 'G2316');
        $this->assertNotNull($result['raw']);
        // Use ASCII-safe substring from the entry
        $this->assertStringContainsString('deity', $result['raw']);
    }

    public function test_strongs_hebrew_h0430_contains_elohim(): void
    {
        $mod = Module::where('key', 'StrongsRealHebrew')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('StrongsRealHebrew not installed');
        }

        $manager = app(SwordManager::class);
        $result = $manager->readDictionaryEntry($mod, 'H0430');
        $this->assertNotNull($result['raw']);
        // Hebrew: Elohim - אלהים
        $this->assertStringContainsString('אלהים', $result['raw']);
    }

    public function test_strongs_greek_driver_name(): void
    {
        $mod = Module::where('key', 'StrongsRealGreek')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('StrongsRealGreek not installed');
        }

        $manager = app(SwordManager::class);
        $reader = $manager->getDictionaryReader($mod);
        $this->assertEquals('rawLD4', $reader->getDriverName());
    }

    public function test_strongs_greek_has_keys(): void
    {
        $mod = Module::where('key', 'StrongsRealGreek')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('StrongsRealGreek not installed');
        }

        $manager = app(SwordManager::class);
        $keys = $manager->getDictionaryKeys($mod);
        $this->assertNotEmpty($keys);
        // Keys are stored without G/H prefix as zero-padded numbers
        $this->assertContains('02316', $keys);
    }
}
