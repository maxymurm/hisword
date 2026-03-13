<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_bookmarks(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/bookmarks');

        $response->assertStatus(200);
    }

    public function test_can_create_bookmark(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/bookmarks', [
                'module_key' => 'KJV',
                'book_osis_id' => 'Gen',
                'chapter_number' => 1,
                'verse_start' => 1,
                'label' => 'Test bookmark',
            ]);

        $response->assertStatus(201);
    }

    public function test_cannot_create_bookmark_without_auth(): void
    {
        $response = $this->postJson('/api/v1/bookmarks', [
            'module_key' => 'KJV',
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_can_delete_bookmark(): void
    {
        $bookmark = Bookmark::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/bookmarks/{$bookmark->id}");

        $response->assertStatus(200);
    }

    public function test_cannot_delete_other_users_bookmark(): void
    {
        $other = User::factory()->create();
        $bookmark = Bookmark::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/bookmarks/{$bookmark->id}");

        $response->assertStatus(404);
    }
}
