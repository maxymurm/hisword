<?php

namespace Tests\Feature\Api;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\Pin;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataExportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Helper ───────────────────────────────────

    private function seedUserData(): array
    {
        $folder = BookmarkFolder::create([
            'user_id' => $this->user->id,
            'name' => 'Favorites',
            'color' => '#FF0000',
        ]);

        $bookmark = Bookmark::create([
            'user_id' => $this->user->id,
            'folder_id' => $folder->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'module_key' => 'KJV',
        ]);

        $highlight = Highlight::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'color' => 'yellow',
            'module_key' => 'KJV',
        ]);

        $note = Note::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'content' => 'My note on Genesis 1:1',
            'module_key' => 'KJV',
        ]);

        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'DARK'],
        ]);

        return compact('folder', 'bookmark', 'highlight', 'note');
    }

    // ── POST /export ─────────────────────────────

    public function test_export_all_data(): void
    {
        $data = $this->seedUserData();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/export');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'app',
                    'schema_version',
                    'exported_at',
                    'user_id',
                    'user_email',
                    'counts',
                    'data' => [
                        'bookmarks',
                        'bookmark_folders',
                        'highlights',
                        'notes',
                        'settings',
                    ],
                ],
            ])
            ->assertJsonPath('data.app', 'HisWord')
            ->assertJsonPath('data.schema_version', '1.0.0');

        $this->assertEquals(1, $response->json('data.counts.bookmarks'));
        $this->assertEquals(1, $response->json('data.counts.bookmark_folders'));
        $this->assertEquals(1, $response->json('data.counts.highlights'));
        $this->assertEquals(1, $response->json('data.counts.notes'));
        $this->assertEquals(1, $response->json('data.counts.settings'));
    }

    public function test_export_selective_types(): void
    {
        $this->seedUserData();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/export', [
                'types' => ['bookmarks', 'highlights'],
            ]);

        $response->assertOk();
        $data = $response->json('data.data');

        $this->assertArrayHasKey('bookmarks', $data);
        $this->assertArrayHasKey('highlights', $data);
        $this->assertArrayNotHasKey('notes', $data);
        $this->assertArrayNotHasKey('settings', $data);
    }

    public function test_export_empty_when_no_data(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/export');

        $response->assertOk();
        $counts = $response->json('data.counts');

        foreach ($counts as $count) {
            $this->assertEquals(0, $count);
        }
    }

    public function test_export_excludes_other_users(): void
    {
        $other = User::factory()->create();
        Bookmark::create([
            'user_id' => $other->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'module_key' => 'KJV',
        ]);

        $this->seedUserData();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/export');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.counts.bookmarks'));
    }

    // ── POST /import/preview ─────────────────────

    public function test_import_preview_shows_counts(): void
    {
        // Export first
        $this->seedUserData();
        $exportResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/export');
        $exportData = $exportResponse->json('data');

        // Delete user data
        $this->user->bookmarks()->forceDelete();
        $this->user->highlights()->forceDelete();

        // Preview import
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/import/preview', [
                'export_data' => $exportData,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'schema_version',
                    'preview',
                ],
            ]);

        // Bookmarks and highlights were deleted so they should show as "new"
        $this->assertEquals(1, $response->json('data.preview.bookmarks.new'));
        $this->assertEquals(1, $response->json('data.preview.highlights.new'));

        // Settings still exist (not deleted) so they should show as conflicts
        $this->assertEquals(1, $response->json('data.preview.settings.conflicts'));
    }

    // ── POST /import ─────────────────────────────

    public function test_import_creates_new_records(): void
    {
        // Create export data from one user
        $this->seedUserData();
        $exportResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/export', ['types' => ['bookmarks', 'bookmark_folders']]);
        $exportData = $exportResponse->json('data');

        // Import into another user
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson('/api/v1/import', [
                'export_data' => $exportData,
                'types' => ['bookmarks', 'bookmark_folders'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.results.bookmarks.imported', 1)
            ->assertJsonPath('data.results.bookmark_folders.imported', 1);

        // Verify records were created for the other user
        $this->assertEquals(1, $otherUser->bookmarks()->count());
    }

    public function test_import_skip_strategy(): void
    {
        $this->seedUserData();
        $exportResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/export', ['types' => ['bookmarks']]);
        $exportData = $exportResponse->json('data');

        // Import same data (should skip all)
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/import', [
                'export_data' => $exportData,
                'conflict_strategy' => 'skip',
                'types' => ['bookmarks'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.results.bookmarks.skipped', 1)
            ->assertJsonPath('data.results.bookmarks.imported', 0);
    }

    public function test_import_overwrite_strategy(): void
    {
        $this->seedUserData();

        // Export
        $exportResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/export', ['types' => ['settings']]);
        $exportData = $exportResponse->json('data');

        // Modify the export data value
        $exportData['data']['settings'][0]['value'] = ['value' => 'OLED'];

        // Import with overwrite
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/import', [
                'export_data' => $exportData,
                'conflict_strategy' => 'overwrite',
                'types' => ['settings'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.results.settings.overwritten', 1);

        $pref = UserPreference::where('user_id', $this->user->id)
            ->where('key', 'ui_theme_variant')
            ->first();
        $this->assertEquals(['value' => 'OLED'], $pref->value);
    }

    public function test_import_validates_schema_version(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/import', [
                'export_data' => [
                    'schema_version' => '99.0.0',
                    'data' => [],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_import_selective_types(): void
    {
        $this->seedUserData();
        $exportResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/export');
        $exportData = $exportResponse->json('data');

        // Import into another user, only settings
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson('/api/v1/import', [
                'export_data' => $exportData,
                'types' => ['settings'],
            ]);

        $response->assertOk();
        $this->assertArrayHasKey('settings', $response->json('data.results'));
        $this->assertArrayNotHasKey('bookmarks', $response->json('data.results'));
    }

    // ── Auth ─────────────────────────────────────

    public function test_unauthenticated_access_rejected(): void
    {
        $this->postJson('/api/v1/export')->assertStatus(401);
        $this->postJson('/api/v1/import', [])->assertStatus(401);
        $this->postJson('/api/v1/import/preview', [])->assertStatus(401);
    }
}
