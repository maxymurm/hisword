<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighlightNoteApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Highlights ──────────────────────────────────────────────

    public function test_can_list_highlights(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/highlights');

        $response->assertStatus(200);
    }

    public function test_can_create_highlight(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/highlights', [
                'module_key' => 'KJV',
                'book_osis_id' => 'Gen',
                'chapter_number' => 1,
                'verse_number' => 1,
                'color' => 'yellow',
            ]);

        $response->assertStatus(201);
    }

    public function test_highlights_require_auth(): void
    {
        $response = $this->getJson('/api/v1/highlights');
        $response->assertStatus(401);
    }

    // ── Notes ───────────────────────────────────────────────────

    public function test_can_list_notes(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notes');

        $response->assertStatus(200);
    }

    public function test_can_create_note(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/notes', [
                'module_key' => 'KJV',
                'book_osis_id' => 'Gen',
                'chapter_number' => 1,
                'verse_start' => 1,
                'content' => 'Study note here',
            ]);

        $response->assertStatus(201);
    }

    public function test_notes_require_auth(): void
    {
        $response = $this->getJson('/api/v1/notes');
        $response->assertStatus(401);
    }
}
