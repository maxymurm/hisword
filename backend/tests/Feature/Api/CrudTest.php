<?php

namespace Tests\Feature\Api;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Bookmark Folders ──────────────────────────

    public function test_can_create_bookmark_folder(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/bookmark-folders', [
                'name' => 'Favorites',
                'color' => '#FF0000',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Favorites')
            ->assertJsonPath('data.color', '#FF0000');
    }

    public function test_can_list_bookmark_folders(): void
    {
        BookmarkFolder::create([
            'user_id' => $this->user->id,
            'name' => 'Folder 1',
            'color' => '#FF0000',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/bookmark-folders');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_update_bookmark_folder(): void
    {
        $folder = BookmarkFolder::create([
            'user_id' => $this->user->id,
            'name' => 'Old Name',
            'color' => '#FF0000',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookmark-folders/{$folder->id}", [
                'name' => 'New Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_can_delete_bookmark_folder(): void
    {
        $folder = BookmarkFolder::create([
            'user_id' => $this->user->id,
            'name' => 'To Delete',
            'color' => '#FF0000',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/bookmark-folders/{$folder->id}")
            ->assertOk();

        // Should be soft-deleted
        $this->assertCount(0, BookmarkFolder::where('id', $folder->id)->get());
    }

    // ── Bookmarks ──────────────────────────────────

    public function test_can_create_bookmark(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/bookmarks', [
                'book_osis_id' => 'Gen',
                'chapter_number' => 1,
                'verse_start' => 1,
                'label' => 'In the beginning',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.book_osis_id', 'Gen');
    }

    public function test_can_list_bookmarks(): void
    {
        Bookmark::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/bookmarks');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_update_bookmark(): void
    {
        $bm = Bookmark::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'label' => 'Old',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/bookmarks/{$bm->id}", [
                'label' => 'Updated',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'Updated');
    }

    public function test_can_delete_bookmark(): void
    {
        $bm = Bookmark::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/bookmarks/{$bm->id}")
            ->assertOk();

        $this->assertCount(0, Bookmark::where('id', $bm->id)->get());
    }

    public function test_bookmark_with_folder(): void
    {
        $folder = BookmarkFolder::create([
            'user_id' => $this->user->id,
            'name' => 'Study',
            'color' => '#00FF00',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/bookmarks', [
                'book_osis_id' => 'Ps',
                'chapter_number' => 23,
                'verse_start' => 1,
                'folder_id' => $folder->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.folder.name', 'Study');
    }

    // ── Highlights ──────────────────────────────────

    public function test_can_create_highlight(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/highlights', [
                'book_osis_id' => 'John',
                'chapter_number' => 3,
                'verse_number' => 16,
                'color' => 'yellow',
                'module_key' => 'KJV',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.color', 'yellow');
    }

    public function test_can_list_highlights(): void
    {
        Highlight::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'color' => 'blue',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/highlights')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_update_highlight_color(): void
    {
        $h = Highlight::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'color' => 'yellow',
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/highlights/{$h->id}", ['color' => 'green'])
            ->assertOk()
            ->assertJsonPath('data.color', 'green');
    }

    // ── Notes ──────────────────────────────────────

    public function test_can_create_note(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/notes', [
                'book_osis_id' => 'Ps',
                'chapter_number' => 23,
                'verse_start' => 1,
                'content' => '# The Lord is my shepherd',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content_format', 'markdown');
    }

    public function test_can_update_note(): void
    {
        $note = Note::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'content' => 'Original',
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/notes/{$note->id}", [
                'content' => 'Updated content',
                'title' => 'My Note',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'My Note');
    }

    // ── Pins ──────────────────────────────────────

    public function test_can_create_pin(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/pins', [
                'book_osis_id' => 'Rom',
                'chapter_number' => 8,
                'verse_number' => 28,
                'module_key' => 'KJV',
                'label' => 'All things work together',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.label', 'All things work together');
    }

    public function test_can_delete_pin(): void
    {
        $pin = Pin::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'module_key' => 'KJV',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/pins/{$pin->id}")
            ->assertOk();

        $this->assertCount(0, Pin::where('id', $pin->id)->get());
    }

    // ── Cross-user isolation ──────────────────────

    public function test_user_cannot_access_others_bookmarks(): void
    {
        $otherUser = User::factory()->create();
        $bm = Bookmark::create([
            'user_id' => $otherUser->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/bookmarks/{$bm->id}")
            ->assertStatus(404);
    }

    public function test_user_cannot_delete_others_highlights(): void
    {
        $otherUser = User::factory()->create();
        $h = Highlight::create([
            'user_id' => $otherUser->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'color' => 'yellow',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/highlights/{$h->id}")
            ->assertStatus(404);
    }
}
