<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\User;
use App\Models\Verse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossReferenceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->module = Module::factory()->create(['key' => 'KJV', 'name' => 'King James Version']);
    }

    private function createVerseWithRefs(
        int $verseNum = 1,
        array $crossRefs = [],
        array $footnotes = [],
    ): Verse {
        return Verse::create([
            'module_id' => $this->module->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => $verseNum,
            'text_raw' => "In the beginning, verse $verseNum.",
            'text_rendered' => "In the beginning, verse $verseNum.",
            'cross_refs' => $crossRefs ?: null,
            'footnotes' => $footnotes ?: null,
        ]);
    }

    // ── GET /cross-references ────────────────────────

    public function test_get_cross_references_for_verse(): void
    {
        $this->createVerseWithRefs(1, [
            ['book' => 'John', 'chapter' => 1, 'verse_start' => 1, 'type' => 'cross-reference'],
            ['book' => 'Heb', 'chapter' => 11, 'verse_start' => 3, 'type' => 'cross-reference'],
        ], [
            ['marker' => 'a', 'text' => 'Or: When God began to create'],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/cross-references?book=Gen&chapter=1&verse=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'verse',
                    'cross_references',
                    'footnotes',
                ],
            ]);

        $this->assertCount(2, $response->json('data.cross_references'));
        $this->assertCount(1, $response->json('data.footnotes'));
        $this->assertEquals('John', $response->json('data.cross_references.0.book'));
    }

    public function test_cross_references_resolve_target_text(): void
    {
        // Create source verse with cross-ref to John 1:1
        $this->createVerseWithRefs(1, [
            ['book' => 'Gen', 'chapter' => 1, 'verse_start' => 2],
        ]);

        // Create the target verse
        $this->createVerseWithRefs(2);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/cross-references?book=Gen&chapter=1&verse=1');

        $response->assertOk();
        $refs = $response->json('data.cross_references');
        $this->assertNotNull($refs[0]['text']);
    }

    public function test_verse_not_found(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/cross-references?book=Xyz&chapter=1&verse=1');

        $response->assertStatus(404);
    }

    public function test_validation_required_params(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/cross-references')
            ->assertStatus(422);
    }

    // ── GET /cross-references/chapter ────────────────

    public function test_get_chapter_cross_references(): void
    {
        // Verse 1: has cross-refs
        $this->createVerseWithRefs(1, [
            ['book' => 'John', 'chapter' => 1, 'verse_start' => 1],
        ]);

        // Verse 2: has footnotes
        $this->createVerseWithRefs(2, [], [
            ['marker' => 'a', 'text' => 'Some footnote'],
        ]);

        // Verse 3: has nothing
        $this->createVerseWithRefs(3);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/cross-references/chapter?book=Gen&chapter=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'book',
                    'chapter',
                    'verses',
                    'total_cross_refs',
                    'total_footnotes',
                ],
            ]);

        // Only verses 1 and 2 should be returned (verse 3 has no refs)
        $this->assertCount(2, $response->json('data.verses'));
        $this->assertEquals(1, $response->json('data.total_cross_refs'));
        $this->assertEquals(1, $response->json('data.total_footnotes'));
    }

    public function test_chapter_empty_when_no_refs(): void
    {
        $this->createVerseWithRefs(1);
        $this->createVerseWithRefs(2);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/cross-references/chapter?book=Gen&chapter=1');

        $response->assertOk();
        $this->assertEmpty($response->json('data.verses'));
    }

    // ── Auth ──────────────────────────────────────────

    public function test_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/cross-references?book=Gen&chapter=1&verse=1')
            ->assertStatus(401);
        $this->getJson('/api/v1/cross-references/chapter?book=Gen&chapter=1')
            ->assertStatus(401);
    }
}
