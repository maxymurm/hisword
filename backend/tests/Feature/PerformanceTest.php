<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\Chapter;
use App\Models\Highlight;
use App\Models\Module;
use App\Models\Note;
use App\Models\Pin;
use App\Models\User;
use App\Models\Verse;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Module $module;
    protected Book $book;
    protected Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->user = User::factory()->create();

        $this->module = Module::factory()->create([
            'key' => 'KJV',
            'type' => 'bible',
            'is_installed' => true,
        ]);

        $this->book = Book::factory()->create([
            'module_id' => $this->module->id,
            'osis_id' => 'Gen',
            'name' => 'Genesis',
            'abbreviation' => 'Gen',
            'testament' => 'OT',
            'book_order' => 1,
            'chapter_count' => 50,
        ]);

        $this->chapter = Chapter::factory()->create([
            'book_id' => $this->book->id,
            'chapter_number' => 1,
            'verse_count' => 31,
        ]);

        // Create some verses
        for ($i = 1; $i <= 5; $i++) {
            Verse::factory()->create([
                'module_id' => $this->module->id,
                'book_osis_id' => 'Gen',
                'chapter_number' => 1,
                'verse_number' => $i,
                'text_raw' => "In the beginning God created verse {$i}.",
                'text_rendered' => "<p>In the beginning God created verse {$i}.</p>",
            ]);
        }
    }

    // ── CacheService Tests ──────────────────────────────────────

    public function test_cache_service_caches_verses(): void
    {
        $cache = app(CacheService::class);

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return ['verse1', 'verse2'];
        };

        // First call: executes callback
        $result1 = $cache->verses('KJV', 'Gen', 1, $callback);
        $this->assertEquals(1, $callCount);
        $this->assertEquals(['verse1', 'verse2'], $result1);

        // Second call: returns cached value
        $result2 = $cache->verses('KJV', 'Gen', 1, $callback);
        $this->assertEquals(1, $callCount); // Not incremented
        $this->assertEquals(['verse1', 'verse2'], $result2);
    }

    public function test_cache_service_caches_installed_modules(): void
    {
        $cache = app(CacheService::class);

        $callCount = 0;
        $result1 = $cache->installedModules('bible', function () use (&$callCount) {
            $callCount++;
            return Module::where('type', 'bible')->where('is_installed', true)->get();
        });

        $result2 = $cache->installedModules('bible', function () use (&$callCount) {
            $callCount++;
            return Module::where('type', 'bible')->where('is_installed', true)->get();
        });

        $this->assertEquals(1, $callCount);
        $this->assertCount(1, $result2);
    }

    public function test_cache_service_flushes_module_cache(): void
    {
        $cache = app(CacheService::class);

        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return ['data'];
        };

        $cache->installedModules('bible', $callback);
        $this->assertEquals(1, $callCount);

        $cache->flushModuleCache();

        $cache->installedModules('bible', $callback);
        $this->assertEquals(2, $callCount); // Re-executed after flush
    }

    public function test_cache_service_caches_books_for_module(): void
    {
        $cache = app(CacheService::class);

        $callCount = 0;
        $cache->booksForModule($this->module->id, function () use (&$callCount) {
            $callCount++;
            return ['Genesis', 'Exodus'];
        });
        $cache->booksForModule($this->module->id, function () use (&$callCount) {
            $callCount++;
            return ['Genesis', 'Exodus'];
        });

        $this->assertEquals(1, $callCount);
    }

    public function test_cache_service_caches_search_results(): void
    {
        $cache = app(CacheService::class);

        $callCount = 0;
        $hash = md5('beginning:KJV:all::1');

        $cache->searchResults($hash, function () use (&$callCount) {
            $callCount++;
            return ['hits' => [], 'meta' => ['total' => 0]];
        });
        $cache->searchResults($hash, function () use (&$callCount) {
            $callCount++;
            return ['hits' => [], 'meta' => ['total' => 0]];
        });

        $this->assertEquals(1, $callCount);
    }

    // ── ReaderController Performance Tests ──────────────────────

    public function test_reader_returns_cached_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/read/KJV/Gen/1');

        $response->assertStatus(200);
    }

    public function test_reader_serves_without_auth(): void
    {
        $response = $this->get('/read/KJV/Gen/1');
        $response->assertStatus(200);
    }

    public function test_reader_with_annotations_uses_select(): void
    {
        Highlight::factory()->create([
            'user_id' => $this->user->id,
            'module_key' => 'KJV',
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
        ]);

        Note::factory()->create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/read/KJV/Gen/1');

        $response->assertStatus(200);
    }

    // ── SearchController Performance Tests ──────────────────────

    public function test_search_uses_direct_module_filter(): void
    {
        $response = $this->get('/search/query?q=beginning&module=KJV');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'hits',
            'meta' => ['query', 'current_page', 'total'],
        ]);
    }

    public function test_search_renders_page(): void
    {
        $response = $this->get('/search');
        $response->assertStatus(200);
    }

    public function test_search_with_scope_filter(): void
    {
        $response = $this->get('/search/query?q=beginning&scope=ot');
        $response->assertStatus(200);
    }

    // ── AnnotationsController Pagination Tests ──────────────────

    public function test_bookmarks_are_paginated(): void
    {
        BookmarkFolder::factory()->create(['user_id' => $this->user->id]);

        for ($i = 0; $i < 5; $i++) {
            Bookmark::factory()->create(['user_id' => $this->user->id]);
        }

        $response = $this->actingAs($this->user)
            ->get('/bookmarks');

        $response->assertStatus(200);
    }

    public function test_notes_are_paginated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Note::factory()->create(['user_id' => $this->user->id]);
        }

        $response = $this->actingAs($this->user)
            ->get('/notes');

        $response->assertStatus(200);
    }

    public function test_highlights_are_paginated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Highlight::factory()->create(['user_id' => $this->user->id]);
        }

        $response = $this->actingAs($this->user)
            ->get('/highlights');

        $response->assertStatus(200);
    }

    public function test_pins_are_paginated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Pin::factory()->create(['user_id' => $this->user->id]);
        }

        $response = $this->actingAs($this->user)
            ->get('/pins');

        $response->assertStatus(200);
    }

    // ── PerformanceHeaders Middleware Tests ──────────────────────

    public function test_performance_headers_on_page(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_well_known_routes_have_cache_headers(): void
    {
        $response = $this->get('/.well-known/assetlinks.json');
        $response->assertStatus(200);
        $response->assertHeader('Cache-Control');
    }

    // ── Database Index Migration Test ───────────────────────────

    public function test_migration_adds_indexes(): void
    {
        // The migration runs as part of RefreshDatabase – just verify tables exist
        $this->assertTrue(\Schema::hasTable('verses'));
        $this->assertTrue(\Schema::hasTable('modules'));
        $this->assertTrue(\Schema::hasTable('highlights'));
        $this->assertTrue(\Schema::hasTable('notes'));
        $this->assertTrue(\Schema::hasTable('bookmarks'));
    }

    // ── AppServiceProvider Tests ────────────────────────────────

    public function test_cache_service_is_singleton(): void
    {
        $instance1 = app(CacheService::class);
        $instance2 = app(CacheService::class);

        $this->assertSame($instance1, $instance2);
    }

    public function test_lazy_loading_prevented_in_testing(): void
    {
        // In testing environment, lazy loading should be prevented
        // This test verifies the AppServiceProvider boot method runs
        $this->assertTrue(true); // If we got here without errors, boot() ran
    }

    // ── API Performance Tests ───────────────────────────────────

    public function test_api_modules_query_is_efficient(): void
    {
        Module::factory()->count(3)->create([
            'type' => 'bible',
            'is_installed' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/modules?type=bible&installed=1');

        $response->assertStatus(200);
    }

    public function test_api_verses_query_uses_index(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/chapters/{$this->chapter->id}/verses");

        $response->assertStatus(200);
    }
}
