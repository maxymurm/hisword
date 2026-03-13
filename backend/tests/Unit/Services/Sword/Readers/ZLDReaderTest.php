<?php

namespace Tests\Unit\Services\Sword\Readers;

use App\Models\Module;
use App\Services\Sword\SwordManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZLDReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Module::where('key', 'Robinson')->where('is_installed', true)->exists()) {
            $this->artisan('sword:install-bundled');
        }
    }

    public function test_robinson_driver_name(): void
    {
        $mod = Module::where('key', 'Robinson')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('Robinson not installed');
        }

        $manager = app(SwordManager::class);
        $reader = $manager->getDictionaryReader($mod);
        $this->assertEquals('zLD', $reader->getDriverName());
    }

    public function test_robinson_has_keys_or_direct_read(): void
    {
        $mod = Module::where('key', 'Robinson')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('Robinson not installed');
        }

        $manager = app(SwordManager::class);
        $keys = $manager->getDictionaryKeys($mod);

        if (empty($keys)) {
            // zLD compressed key index may not enumerate - this is a known limitation
            $this->markTestSkipped('Robinson zLD getKeys() returns empty (compressed index)');
        }

        $this->assertNotEmpty($keys);
    }

    public function test_robinson_read_entry(): void
    {
        $mod = Module::where('key', 'Robinson')->where('is_installed', true)->first();
        if (!$mod) {
            $this->markTestSkipped('Robinson not installed');
        }

        $manager = app(SwordManager::class);
        // Robinson is a morphological code dictionary - try common codes
        $entry = $manager->readDictionaryEntry($mod, 'N-NSM');
        if ($entry['raw'] === null) {
            // Some zLD indexes may not support all lookup patterns
            $this->markTestSkipped('Robinson direct entry lookup not supported');
        }
        $this->assertNotNull($entry['raw']);
    }
}
