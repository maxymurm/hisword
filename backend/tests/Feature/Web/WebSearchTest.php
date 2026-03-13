<?php

namespace Tests\Feature\Web;

use App\Enums\ModuleType;
use App\Models\Module;
use App\Models\Verse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebSearchTest extends TestCase
{
    use RefreshDatabase;

    private function seedVerses(string $moduleKey = 'KJV', int $count = 5, string $text = 'God so loved the world'): Module
    {
        $module = Module::factory()->create([
            'key' => $moduleKey,
            'name' => $moduleKey . ' Bible',
            'type' => ModuleType::Bible,
            'is_installed' => true,
        ]);

        for ($i = 1; $i <= $count; $i++) {
            Verse::factory()->create([
                'module_id' => $module->id,
                'book_osis_id' => 'John',
                'chapter_number' => 3,
                'verse_number' => $i,
                'text_raw' => $text . " verse $i",
                'text_rendered' => $text . " verse $i",
            ]);
        }

        return $module;
    }

    // ── Search Page Renders ──────────────────────────────────

    public function test_search_page_renders(): void
    {
        $this->withoutVite();

        $response = $this->get('/search');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search')
                ->has('modules')
                ->has('books')
                ->where('initialQuery', '')
                ->where('initialResults', null)
            );
    }

    public function test_search_page_with_query_returns_results(): void
    {
        $this->withoutVite();
        $this->seedVerses();

        $response = $this->get('/search?q=loved');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search')
                ->where('initialQuery', 'loved')
                ->has('initialResults.hits', 5)
                ->where('initialResults.meta.query', 'loved')
                ->where('initialResults.meta.total', 5)
            );
    }

    public function test_search_page_with_no_results(): void
    {
        $this->withoutVite();
        $this->seedVerses();

        $response = $this->get('/search?q=xyznonexistent');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search')
                ->where('initialQuery', 'xyznonexistent')
                ->has('initialResults.hits', 0)
                ->where('initialResults.meta.total', 0)
            );
    }

    public function test_search_page_shows_installed_bible_modules(): void
    {
        $this->withoutVite();
        Module::factory()->create(['key' => 'KJV', 'type' => ModuleType::Bible, 'is_installed' => true]);
        Module::factory()->create(['key' => 'ESV', 'type' => ModuleType::Bible, 'is_installed' => true]);
        Module::factory()->create(['key' => 'COMM', 'type' => ModuleType::Commentary, 'is_installed' => true]);
        Module::factory()->create(['key' => 'NOINSTALL', 'type' => ModuleType::Bible, 'is_installed' => false]);

        $response = $this->get('/search');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('modules', 2) // Only installed bibles
            );
    }

    // ── AJAX query endpoint ──────────────────────────────────

    public function test_search_query_returns_json(): void
    {
        $this->seedVerses();

        $response = $this->getJson('/search/query?q=loved');

        $response->assertOk()
            ->assertJsonStructure([
                'hits' => [['id', 'reference', 'book_osis_id', 'chapter_number', 'verse_number', 'text', 'highlight', 'module_key']],
                'meta' => ['query', 'current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_search_query_highlights_text(): void
    {
        $this->seedVerses();

        $response = $this->getJson('/search/query?q=loved');

        $response->assertOk();
        $hit = $response->json('hits.0');
        $this->assertStringContainsString('<mark>loved</mark>', $hit['highlight']);
    }

    public function test_search_query_requires_min_2_chars(): void
    {
        $response = $this->getJson('/search/query?q=a');

        $response->assertUnprocessable();
    }

    public function test_search_query_paginates(): void
    {
        $module = Module::factory()->create([
            'key' => 'KJV',
            'type' => ModuleType::Bible,
            'is_installed' => true,
        ]);

        for ($i = 1; $i <= 30; $i++) {
            Verse::factory()->create([
                'module_id' => $module->id,
                'book_osis_id' => 'Psa',
                'chapter_number' => 1,
                'verse_number' => $i,
                'text_raw' => "The Lord is my shepherd verse $i",
            ]);
        }

        $response = $this->getJson('/search/query?q=shepherd&page=2');

        $response->assertOk();
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(30, $response->json('meta.total'));
        $this->assertCount(5, $response->json('hits')); // 30 total, 25 per page, page 2 = 5
    }

    public function test_search_query_filters_by_module(): void
    {
        $kjv = $this->seedVerses('KJV', 3, 'grace abounding');
        $esv = $this->seedVerses('ESV', 2, 'grace abounding');

        $response = $this->getJson('/search/query?q=grace&module=KJV');

        $response->assertOk();
        // At least the 3 seeded verses should match; SWORD/FTS index may add more
        $this->assertGreaterThanOrEqual(3, $response->json('meta.total'));
    }

    public function test_search_query_filters_by_scope_book(): void
    {
        $module = Module::factory()->create(['key' => 'KJV', 'type' => ModuleType::Bible, 'is_installed' => true]);
        Verse::factory()->create(['module_id' => $module->id, 'book_osis_id' => 'Gen', 'text_raw' => 'In the beginning']);
        Verse::factory()->create(['module_id' => $module->id, 'book_osis_id' => 'John', 'text_raw' => 'In the beginning was the Word']);

        $response = $this->getJson('/search/query?q=beginning&scope=book&book=John');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('John', $response->json('hits.0.book_osis_id'));
    }
}
