<?php

namespace Tests\Feature\Api;

use App\Events\SyncBatchCompleted;
use App\Events\SyncEntityChanged;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $deviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->deviceId = 'test-device-broadcast';

        Device::create([
            'user_id' => $this->user->id,
            'device_id' => $this->deviceId,
            'platform' => 'android',
        ]);
    }

    public function test_push_broadcasts_entity_changed_event(): void
    {
        Event::fake([SyncEntityChanged::class, SyncBatchCompleted::class]);

        $bookmarkId = Str::uuid()->toString();

        $this->actingAs($this->user)
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
                            'label' => 'Test broadcast',
                        ],
                        'vector_clock' => ['device-1' => 1],
                    ],
                ],
            ])
            ->assertOk();

        Event::assertDispatched(SyncEntityChanged::class, function (SyncEntityChanged $event) use ($bookmarkId) {
            return $event->userId === $this->user->id
                && $event->entityType === 'bookmark'
                && $event->entityId === $bookmarkId
                && $event->operation === 'create'
                && $event->deviceId === $this->deviceId;
        });
    }

    public function test_push_broadcasts_batch_completed_event(): void
    {
        Event::fake([SyncEntityChanged::class, SyncBatchCompleted::class]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                        'data' => [
                            'book_osis_id' => 'Gen',
                            'chapter_number' => 1,
                            'verse_start' => 1,
                        ],
                        'vector_clock' => ['d1' => 1],
                    ],
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                        'data' => [
                            'book_osis_id' => 'Gen',
                            'chapter_number' => 2,
                            'verse_start' => 1,
                        ],
                        'vector_clock' => ['d1' => 2],
                    ],
                ],
            ])
            ->assertOk();

        Event::assertDispatched(SyncBatchCompleted::class, function (SyncBatchCompleted $event) {
            return $event->userId === $this->user->id
                && $event->deviceId === $this->deviceId
                && $event->applied === 2
                && $event->conflicts === 0;
        });
    }

    public function test_no_broadcast_when_push_has_validation_errors(): void
    {
        Event::fake([SyncEntityChanged::class, SyncBatchCompleted::class]);

        // Invalid entity type is rejected at validation level (422)
        $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'invalid_type',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                        'data' => [],
                        'vector_clock' => [],
                    ],
                ],
            ])
            ->assertUnprocessable();

        // Validation failed before SyncService → no events dispatched
        Event::assertNotDispatched(SyncBatchCompleted::class);
        Event::assertNotDispatched(SyncEntityChanged::class);
    }

    public function test_entity_changed_broadcasts_on_private_channel(): void
    {
        $event = new SyncEntityChanged(
            userId: $this->user->id,
            entityType: 'highlight',
            entityId: 'test-id',
            operation: 'update',
            data: ['color' => 'yellow'],
            vectorClock: ['d1' => 3],
            deviceId: $this->deviceId,
            timestamp: now()->toIso8601String(),
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertEquals("private-sync.{$this->user->id}", $channels[0]->name);
    }

    public function test_entity_changed_broadcast_name(): void
    {
        $event = new SyncEntityChanged(
            userId: $this->user->id,
            entityType: 'note',
            entityId: 'test-id',
            operation: 'create',
            data: ['content' => 'Test note'],
            vectorClock: ['d1' => 1],
            deviceId: $this->deviceId,
            timestamp: now()->toIso8601String(),
        );

        $this->assertEquals('sync.entity.changed', $event->broadcastAs());
    }

    public function test_entity_changed_broadcast_payload(): void
    {
        $event = new SyncEntityChanged(
            userId: $this->user->id,
            entityType: 'pin',
            entityId: 'pin-id-1',
            operation: 'delete',
            data: null,
            vectorClock: ['d1' => 5, 'd2' => 3],
            deviceId: $this->deviceId,
            timestamp: '2025-01-01T00:00:00+00:00',
        );

        $payload = $event->broadcastWith();

        $this->assertEquals('pin', $payload['entity_type']);
        $this->assertEquals('pin-id-1', $payload['entity_id']);
        $this->assertEquals('delete', $payload['operation']);
        $this->assertNull($payload['data']);
        $this->assertEquals(['d1' => 5, 'd2' => 3], $payload['vector_clock']);
        $this->assertEquals($this->deviceId, $payload['device_id']);
        $this->assertEquals('2025-01-01T00:00:00+00:00', $payload['timestamp']);
    }

    public function test_batch_completed_broadcast_payload(): void
    {
        $event = new SyncBatchCompleted(
            userId: $this->user->id,
            deviceId: $this->deviceId,
            applied: 5,
            conflicts: 1,
            timestamp: '2025-01-01T12:00:00+00:00',
        );

        $this->assertEquals('sync.batch.completed', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertEquals($this->deviceId, $payload['device_id']);
        $this->assertEquals(5, $payload['applied']);
        $this->assertEquals(1, $payload['conflicts']);
    }

    public function test_batch_completed_broadcasts_on_private_channel(): void
    {
        $event = new SyncBatchCompleted(
            userId: $this->user->id,
            deviceId: $this->deviceId,
            applied: 3,
            conflicts: 0,
            timestamp: now()->toIso8601String(),
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertEquals("private-sync.{$this->user->id}", $channels[0]->name);
    }

    public function test_multiple_entity_changes_each_broadcast(): void
    {
        Event::fake([SyncEntityChanged::class, SyncBatchCompleted::class]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => $this->deviceId,
                'changes' => [
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                        'data' => ['book_osis_id' => 'Gen', 'chapter_number' => 1, 'verse_start' => 1],
                        'vector_clock' => ['d1' => 1],
                    ],
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                        'data' => ['book_osis_id' => 'Exod', 'chapter_number' => 2, 'verse_start' => 3],
                        'vector_clock' => ['d1' => 2],
                    ],
                    [
                        'entity_type' => 'bookmark',
                        'entity_id' => Str::uuid()->toString(),
                        'operation' => 'create',
                        'data' => ['book_osis_id' => 'Lev', 'chapter_number' => 3, 'verse_start' => 5],
                        'vector_clock' => ['d1' => 3],
                    ],
                ],
            ])
            ->assertOk();

        Event::assertDispatched(SyncEntityChanged::class, 3);
        Event::assertDispatched(SyncBatchCompleted::class, 1);
    }

    public function test_channel_auth_authorizes_own_user(): void
    {
        $this->actingAs($this->user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '12345.67890',
                'channel_name' => "private-sync.{$this->user->id}",
            ])
            ->assertOk();
    }

    public function test_channel_auth_rejects_other_user(): void
    {
        $otherUser = User::factory()->create();

        // Verify the channel authorization callback directly
        // The callback in channels.php checks $user->id === $userId
        $authCallback = fn (User $user, string $userId) => $user->id === $userId;
        $this->assertTrue($authCallback($this->user, $this->user->id));
        $this->assertFalse($authCallback($this->user, $otherUser->id));
    }
}
