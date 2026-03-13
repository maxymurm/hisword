<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BibleSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $this->module = Module::create([
            'key' => 'KJV',
            'name' => 'King James Version',
            'type' => 'bible',
            'language' => 'en',
            'version' => '1.0',
        ]);

        $book = Book::create([
            'module_id' => $this->module->id,
            'osis_id' => 'Gen',
            'name' => 'Genesis',
            'abbreviation' => 'Gen',
            'book_order' => 1,
            'testament' => 'OT',
            'chapter_count' => 50,
        ]);

        $chapter = Chapter::create([
            'book_id' => $book->id,
            'chapter_number' => 1,
            'verse_count' => 31,
        ]);

        Verse::create([
            'module_id' => $this->module->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'text_raw' => 'In the beginning God created the heaven and the earth.',
        ]);

        Verse::create([
            'module_id' => $this->module->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 2,
            'text_raw' => 'And the earth was without form, and void.',
        ]);

        // Add a NT verse for testament filtering tests
        $ntBook = Book::create([
            'module_id' => $this->module->id,
            'osis_id' => 'John',
            'name' => 'John',
            'abbreviation' => 'Jhn',
            'book_order' => 43,
            'testament' => 'NT',
            'chapter_count' => 21,
        ]);

        $ntChapter = Chapter::create([
            'book_id' => $ntBook->id,
            'chapter_number' => 1,
            'verse_count' => 51,
        ]);

        Verse::create([
            'module_id' => $this->module->id,
            'book_osis_id' => 'John',
            'chapter_number' => 1,
            'verse_number' => 1,
            'text_raw' => 'In the beginning was the Word, and the Word was with God, and the Word was God.',
        ]);
    }

    // ── Bible Content ─────────────────────────

    public function test_can_list_books(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/modules/KJV/books')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_chapters(): void
    {
        $book = Book::where('osis_id', 'Gen')->first();

        $this->actingAs($this->user)
            ->getJson("/api/v1/books/{$book->id}/chapters")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_verses(): void
    {
        $chapter = Chapter::where('verse_count', 31)->first();

        $this->actingAs($this->user)
            ->getJson("/api/v1/chapters/{$chapter->id}/verses")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_single_verse(): void
    {
        $verse = Verse::where('verse_number', 1)->where('book_osis_id', 'Gen')->first();

        $this->actingAs($this->user)
            ->getJson("/api/v1/verses/{$verse->id}")
            ->assertOk()
            ->assertJsonPath('data.text_raw', 'In the beginning God created the heaven and the earth.');
    }

    // ── Modules ──────────────────────────────

    public function test_can_list_modules(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/modules')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'KJV');
    }

    public function test_can_show_module(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/modules/{$this->module->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'King James Version');
    }

    // ── Search (SQL fallback — no Meilisearch in test env) ──

    public function test_can_search_verses(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=beginning&module=KJV');

        $response->assertOk()
            ->assertJsonCount(2, 'data'); // Gen 1:1 + John 1:1 both match
    }

    public function test_search_requires_query(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/search')
            ->assertStatus(422);
    }

    public function test_search_requires_minimum_length(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=a')
            ->assertStatus(422);
    }

    public function test_search_by_book(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=earth&module=KJV&scope=book&book=Gen');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_search_by_ot_scope(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=beginning&scope=ot');

        $response->assertOk()
            ->assertJsonCount(1, 'data'); // Only Gen 1:1
    }

    public function test_search_by_nt_scope(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=beginning&scope=nt');

        $response->assertOk()
            ->assertJsonCount(1, 'data'); // Only John 1:1
    }

    public function test_search_no_results(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=xyznonexistent&module=KJV')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_pagination(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=the&per_page=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_search_returns_expected_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search?q=beginning');

        $response->assertOk();
        $data = $response->json('data.0');

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('book_osis_id', $data);
        $this->assertArrayHasKey('chapter_number', $data);
        $this->assertArrayHasKey('verse_number', $data);
        $this->assertArrayHasKey('text_raw', $data);
    }

    // ── Suggestions ──────────────────────────

    public function test_search_suggest_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/search/suggest?q=beginning');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_search_suggest_requires_query(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/search/suggest')
            ->assertStatus(422);
    }

    // ── Auth required ────────────────────────

    public function test_bible_routes_require_auth(): void
    {
        $this->getJson('/api/v1/modules/KJV/books')->assertUnauthorized();
        $this->getJson('/api/v1/modules')->assertUnauthorized();
        $this->getJson('/api/v1/search?q=test')->assertUnauthorized();
        $this->getJson('/api/v1/search/suggest?q=test')->assertUnauthorized();
    }

    // ── Verse Model Searchable ───────────────

    public function test_verse_has_searchable_array(): void
    {
        $verse = Verse::where('verse_number', 1)->where('book_osis_id', 'Gen')->first();
        $searchable = $verse->toSearchableArray();

        $this->assertEquals('In the beginning God created the heaven and the earth.', $searchable['text']);
        $this->assertEquals('Gen', $searchable['book_osis_id']);
        $this->assertEquals('Genesis', $searchable['book_name']);
        $this->assertEquals(1, $searchable['chapter_number']);
        $this->assertEquals(1, $searchable['verse_number']);
        $this->assertEquals('OT', $searchable['testament']);
        $this->assertEquals('Genesis 1:1', $searchable['reference']);
    }

    public function test_verse_searchable_index_name(): void
    {
        $verse = new Verse();
        $this->assertEquals('verses', $verse->searchableAs());
    }

    public function test_nt_verse_testament_mapping(): void
    {
        $verse = Verse::where('book_osis_id', 'John')->first();
        $searchable = $verse->toSearchableArray();

        $this->assertEquals('NT', $searchable['testament']);
        $this->assertEquals('John 1:1', $searchable['reference']);
    }
}
