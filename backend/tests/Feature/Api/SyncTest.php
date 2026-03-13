<?php

namespace Tests\Feature\Api;

use App\Models\Bookmark;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $deviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->deviceId = 'test-device-1';

        Device::create([
            'user_id' => $this->user->id,
            'device_id' => $this->deviceId,
            'platform' => 'android',
        ]);
    }

    public function test_push_creates_new_entity(): void
    {
        $bookmarkId = Str::uuid()->toString();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => $bookmarkId,
                        'operation' => 'create',
                        'data' => [
                            'book_osis_id' => 'Gen',
                            'chapter_number' => 1,
                            'verse_start' => 1,
                            'label' => 'In the beginning',
                        ],
                        'vector_clock' => ['device-1' => 1],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.applied', 1)
            ->assertJsonPath('data.conflicts', 0);

        $this->assertDatabaseHas('bookmarks', [
            'id' => $bookmarkId,
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
        ]);
    }

    public function test_push_updates_existing_entity(): void
    {
        $bookmark = Bookmark::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'label' => 'Original',
            'vector_clock' => ['device-1' => 1],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => $bookmark->id,
                        'operation' => 'update',
                        'data' => [
                            'label' => 'Updated',
                        ],
                        'vector_clock' => ['device-1' => 2],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.applied', 1);

        $bookmark->refresh();
        $this->assertEquals('Updated', $bookmark->label);
    }

    public function test_push_deletes_entity(): void
    {
        $bookmark = Bookmark::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'vector_clock' => ['device-1' => 1],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => $bookmark->id,
                        'operation' => 'delete',
                        'vector_clock' => ['device-1' => 2],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.applied', 1);

        // Should be soft-deleted (not visible in default scope)
        $this->assertCount(0, Bookmark::where('id', $bookmark->id)->get());
        // But visible with deleted scope
        $deleted = Bookmark::withDeleted()->find($bookmark->id);
        $this->assertTrue($deleted->is_deleted);
    }

    public function test_push_handles_lww_conflict(): void
    {
        // Server has newer data
        $bookmark = Bookmark::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'label' => 'Server Label',
            'vector_clock' => ['device-1' => 5, 'device-2' => 3],
        ]);

        // Client sends older clock
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => $bookmark->id,
                        'operation' => 'update',
                        'data' => ['label' => 'Client Label'],
                        'vector_clock' => ['device-1' => 2],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.conflicts', 1);

        // Server label should win (LWW)
        $bookmark->refresh();
        $this->assertEquals('Server Label', $bookmark->label);
    }

    public function test_pull_returns_changes_since_last_sync(): void
    {
        $device2 = 'device-2';
        Device::create([
            'user_id' => $this->user->id,
            'device_id' => $device2,
            'platform' => 'ios',
        ]);

        // Push changes from device 2
        $bookmarkId = Str::uuid()->toString();
        $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $device2,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => $bookmarkId,
                        'operation' => 'create',
                        'data' => [
                            'book_osis_id' => 'John',
                            'chapter_number' => 3,
                            'verse_start' => 16,
                        ],
                        'vector_clock' => ['device-2' => 1],
                    ],
                ],
            ]);

        // Pull changes from device 1
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/pull', [
                'device_id' => $this->deviceId,
                'last_sync_at' => now()->subHour()->toIso8601String(),
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'changes' => [['entity_type', 'entity_id', 'operation', 'data']],
                    'server_time',
                    'has_more',
                ],
            ]);

        $changes = $response->json('data.changes');
        $this->assertCount(1, $changes);
        $this->assertEquals('bookmark', $changes[0]['entity_type']);
        $this->assertEquals($bookmarkId, $changes[0]['entity_id']);
    }

    public function test_pull_filters_by_entity_type(): void
    {
        $device2 = 'device-2';
        Device::create([
            'user_id' => $this->user->id,
            'device_id' => $device2,
            'platform' => 'ios',
        ]);

        // Push a bookmark and a highlight
        $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $device2,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                        'data' => ['book_osis_id' => 'Gen', 'chapter_number' => 1, 'verse_start' => 1],
                        'vector_clock' => ['device-2' => 1],
                    ],
                    [
                        'entity_type' => 'highlight',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                        'data' => ['book_osis_id' => 'Gen', 'chapter_number' => 1, 'verse_number' => 1, 'color' => 'yellow'],
                        'vector_clock' => ['device-2' => 2],
                    ],
                ],
            ]);

        // Pull only bookmarks
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/pull', [
                'device_id' => $this->deviceId,
                'last_sync_at' => now()->subHour()->toIso8601String(),
                'entity_types' => ['bookmark'],
            ]);

        $changes = $response->json('data.changes');
        $this->assertCount(1, $changes);
        $this->assertEquals('bookmark', $changes[0]['entity_type']);
    }

    public function test_pull_excludes_own_device_changes(): void
    {
        // Push from same device
        $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                        'data' => ['book_osis_id' => 'Gen', 'chapter_number' => 1, 'verse_start' => 1],
                        'vector_clock' => ['device-1' => 1],
                    ],
                ],
            ]);

        // Pull from same device should get nothing
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/pull', [
                'device_id' => $this->deviceId,
                'last_sync_at' => now()->subHour()->toIso8601String(),
            ]);

        $this->assertCount(0, $response->json('data.changes'));
    }

    public function test_push_validates_input(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_id', 'changes']);
    }

    public function test_push_validates_entity_type(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'invalid_type',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_delete_wins_over_update(): void
    {
        $bookmark = Bookmark::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_start' => 1,
            'is_deleted' => true,
            'deleted_at' => now(),
            'vector_clock' => ['device-1' => 3],
        ]);

        // Try to update a deleted entity
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => $bookmark->id,
                        'operation' => 'update',
                        'data' => ['label' => 'Should not apply'],
                        'vector_clock' => ['device-2' => 5],
                    ],
                ],
            ]);

        $response->assertOk();

        // Entity should still be deleted
        $bookmark = Bookmark::withDeleted()->find($bookmark->id);
        $this->assertTrue($bookmark->is_deleted);
    }

    public function test_sync_requires_authentication(): void
    {
        $this->postJson('/api/v1/sync/push', [])->assertStatus(401);
        $this->postJson('/api/v1/sync/pull', [])->assertStatus(401);
    }
}
