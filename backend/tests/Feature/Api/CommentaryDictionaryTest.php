<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\Verse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentaryDictionaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Module $commentaryModule;
    private Module $dictionaryModule;
    private Module $bibleModule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $this->bibleModule = Module::create([
            'key' => 'KJV',
            'name' => 'King James Version',
            'type' => 'bible',
            'language' => 'en',
            'version' => '1.0',
        ]);

        $this->commentaryModule = Module::create([
            'key' => 'MHC',
            'name' => 'Matthew Henry Commentary',
            'type' => 'commentary',
            'language' => 'en',
            'version' => '1.0',
            'description' => 'A comprehensive commentary by Matthew Henry.',
        ]);

        $this->dictionaryModule = Module::create([
            'key' => 'StrongsHebrew',
            'name' => "Strong's Hebrew Dictionary",
            'type' => 'dictionary',
            'language' => 'en',
            'version' => '1.0',
            'description' => "Strong's Concordance Hebrew dictionary.",
        ]);

        // Commentary entries
        Verse::create([
            'module_id' => $this->commentaryModule->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'text_raw' => 'In the beginning - This is the first verse of the Bible and establishes the foundation of creation.',
        ]);

        Verse::create([
            'module_id' => $this->commentaryModule->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 2,
            'text_raw' => 'And the earth was without form - The earth existed but in a chaotic state.',
        ]);

        Verse::create([
            'module_id' => $this->commentaryModule->id,
            'book_osis_id' => 'John',
            'chapter_number' => 3,
            'verse_number' => 16,
            'text_raw' => 'For God so loved the world - The central message of the Gospel.',
            'text_rendered' => '<p>For God so loved the world - The central message of the Gospel.</p>',
        ]);

        // Dictionary entries
        Verse::create([
            'module_id' => $this->dictionaryModule->id,
            'book_osis_id' => 'H7225',
            'chapter_number' => 0,
            'verse_number' => 0,
            'text_raw' => 'reshith: beginning, first, chief. From rosh (H7218).',
        ]);

        Verse::create([
            'module_id' => $this->dictionaryModule->id,
            'book_osis_id' => 'H430',
            'chapter_number' => 0,
            'verse_number' => 0,
            'text_raw' => 'elohim: God, gods, judges. Plural of eloah.',
        ]);

        Verse::create([
            'module_id' => $this->dictionaryModule->id,
            'book_osis_id' => 'H776',
            'chapter_number' => 0,
            'verse_number' => 0,
            'text_raw' => 'erets: earth, land, ground. From an unused root.',
        ]);
    }

    // ── Commentary: Index ─────────────────────

    public function test_can_list_commentaries(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/commentaries')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'MHC')
            ->assertJsonPath('data.0.name', 'Matthew Henry Commentary');
    }

    public function test_commentary_index_excludes_non_commentary_modules(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/commentaries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['key' => 'KJV'])
            ->assertJsonMissing(['key' => 'StrongsHebrew']);
    }

    // ── Commentary: Show ──────────────────────

    public function test_can_show_commentary_module(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/commentaries/{$this->commentaryModule->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.key', 'MHC');
    }

    public function test_show_rejects_non_commentary_module(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/commentaries/{$this->bibleModule->id}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ── Commentary: Entry ─────────────────────

    public function test_can_get_commentary_for_chapter(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/commentaries/{$this->commentaryModule->id}/entry?book=Gen&chapter=1")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.entries')
            ->assertJsonPath('data.module.key', 'MHC');
    }

    public function test_can_get_commentary_for_specific_verse(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/commentaries/{$this->commentaryModule->id}/entry?book=Gen&chapter=1&verse=1")
            ->assertOk()
            ->assertJsonCount(1, 'data.entries')
            ->assertJsonPath('data.entries.0.verse', 1)
            ->assertJsonPath('data.entries.0.book_osis_id', 'Gen');
    }

    public function test_commentary_entry_prefers_rendered_text(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/commentaries/{$this->commentaryModule->id}/entry?book=John&chapter=3&verse=16")
            ->assertOk()
            ->assertJsonPath('data.entries.0.text', '<p>For God so loved the world - The central message of the Gospel.</p>');
    }

    public function test_commentary_entry_includes_reference(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/commentaries/{$this->commentaryModule->id}/entry?book=Gen&chapter=1&verse=1")
            ->assertOk()
            ->assertJsonPath('data.entries.0.reference', 'Genesis 1:1');
    }

    public function test_commentary_entry_requires_book_and_chapter(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/commentaries/{$this->commentaryModule->id}/entry")
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->getJson("/api/v1/commentaries/{$this->commentaryModule->id}/entry?book=Gen")
            ->assertStatus(422);
    }

    public function test_commentary_entry_rejects_non_commentary(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/commentaries/{$this->bibleModule->id}/entry?book=Gen&chapter=1")
            ->assertStatus(404);
    }

    // ── Dictionary: Index ─────────────────────

    public function test_can_list_dictionaries(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/dictionaries')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'StrongsHebrew');
    }

    public function test_dictionary_index_excludes_non_dictionary_modules(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/dictionaries')
            ->assertOk()
            ->assertJsonMissing(['key' => 'KJV'])
            ->assertJsonMissing(['key' => 'MHC']);
    }

    // ── Dictionary: Show ──────────────────────

    public function test_can_show_dictionary_module(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/dictionaries/{$this->dictionaryModule->id}")
            ->assertOk()
            ->assertJsonPath('data.key', 'StrongsHebrew');
    }

    public function test_show_rejects_non_dictionary_module(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/dictionaries/{$this->bibleModule->id}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ── Dictionary: Entry ─────────────────────

    public function test_can_get_dictionary_entry(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/dictionaries/{$this->dictionaryModule->id}/entry/H7225")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.key', 'H7225')
            ->assertJsonPath('data.module.key', 'StrongsHebrew')
            ->assertJsonFragment(['text' => 'reshith: beginning, first, chief. From rosh (H7218).']);
    }

    public function test_dictionary_entry_returns_404_for_missing_key(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/dictionaries/{$this->dictionaryModule->id}/entry/H9999")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_dictionary_entry_rejects_non_dictionary(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/dictionaries/{$this->bibleModule->id}/entry/H7225")
            ->assertStatus(404);
    }

    // ── Dictionary: Browse ────────────────────

    public function test_can_browse_dictionary_entries(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/dictionaries/{$this->dictionaryModule->id}/entries")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_can_search_dictionary_entries(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/dictionaries/{$this->dictionaryModule->id}/entries?q=H7")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // H7225 and H776
    }

    public function test_dictionary_entries_paginated(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/dictionaries/{$this->dictionaryModule->id}/entries?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_dictionary_entries_rejects_non_dictionary(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/dictionaries/{$this->bibleModule->id}/entries")
            ->assertStatus(404);
    }

    // ── Auth Required ─────────────────────────

    public function test_commentary_routes_require_auth(): void
    {
        $this->getJson('/api/v1/commentaries')->assertUnauthorized();
        $this->getJson("/api/v1/commentaries/{$this->commentaryModule->id}")->assertUnauthorized();
        $this->getJson("/api/v1/commentaries/{$this->commentaryModule->id}/entry?book=Gen&chapter=1")->assertUnauthorized();
    }

    public function test_dictionary_routes_require_auth(): void
    {
        $this->getJson('/api/v1/dictionaries')->assertUnauthorized();
        $this->getJson("/api/v1/dictionaries/{$this->dictionaryModule->id}")->assertUnauthorized();
        $this->getJson("/api/v1/dictionaries/{$this->dictionaryModule->id}/entry/H7225")->assertUnauthorized();
        $this->getJson("/api/v1/dictionaries/{$this->dictionaryModule->id}/entries")->assertUnauthorized();
    }
}
