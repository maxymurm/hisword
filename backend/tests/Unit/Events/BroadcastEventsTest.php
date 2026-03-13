<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\MarkerChanged;
use App\Events\PlanProgressChanged;
use App\Events\SyncBatchCompleted;
use App\Events\SyncEntityChanged;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\TestCase;

class BroadcastEventsTest extends TestCase
{
    // ── MarkerChanged ────────────────────────────

    public function test_marker_changed_broadcasts_on_private_sync_channel(): void
    {
        $event = new MarkerChanged(
            userId: 'user-123',
            kind: 0,
            gid: 'gid-456',
            operation: 'create',
            data: ['book_osis_id' => 'Gen'],
            deviceId: 'device-1',
            timestamp: '2026-01-01T00:00:00Z',
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    public function test_marker_changed_broadcast_name(): void
    {
        $event = new MarkerChanged(
            userId: 'u', kind: 0, gid: 'g', operation: 'create',
            data: null, deviceId: 'd', timestamp: 't',
        );

        $this->assertSame('marker.changed', $event->broadcastAs());
    }

    public function test_marker_changed_broadcast_payload(): void
    {
        $event = new MarkerChanged(
            userId: 'u',
            kind: 2,
            gid: 'highlight-id',
            operation: 'update',
            data: ['color' => 'blue'],
            deviceId: 'phone-1',
            timestamp: '2026-06-15T12:00:00Z',
        );

        $payload = $event->broadcastWith();

        $this->assertSame(2, $payload['kind']);
        $this->assertSame('highlight-id', $payload['gid']);
        $this->assertSame('update', $payload['operation']);
        $this->assertSame(['color' => 'blue'], $payload['data']);
        $this->assertSame('phone-1', $payload['device_id']);
    }

    // ── PlanProgressChanged ──────────────────────

    public function test_plan_progress_broadcasts_on_private_channel(): void
    {
        $event = new PlanProgressChanged(
            userId: 'user-1',
            planId: 'plan-1',
            dayNumber: 7,
            completed: true,
            deviceId: 'dev-1',
            timestamp: '2026-01-07T00:00:00Z',
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    public function test_plan_progress_broadcast_name(): void
    {
        $event = new PlanProgressChanged(
            userId: 'u', planId: 'p', dayNumber: 1,
            completed: false, deviceId: 'd', timestamp: 't',
        );

        $this->assertSame('plan.progress.changed', $event->broadcastAs());
    }

    public function test_plan_progress_payload(): void
    {
        $event = new PlanProgressChanged(
            userId: 'u',
            planId: 'bible-in-year',
            dayNumber: 30,
            completed: true,
            deviceId: 'tablet-1',
            timestamp: '2026-01-30T08:00:00Z',
        );

        $payload = $event->broadcastWith();

        $this->assertSame('bible-in-year', $payload['plan_id']);
        $this->assertSame(30, $payload['day_number']);
        $this->assertTrue($payload['completed']);
        $this->assertSame('tablet-1', $payload['device_id']);
    }

    // ── SyncEntityChanged (existing) ─────────────

    public function test_sync_entity_changed_broadcast_name(): void
    {
        $event = new SyncEntityChanged(
            userId: 'u', entityType: 'bookmark', entityId: 'e',
            operation: 'create', data: null, vectorClock: [],
            deviceId: 'd', timestamp: 't',
        );

        $this->assertSame('sync.entity.changed', $event->broadcastAs());
    }

    public function test_sync_entity_changed_channel(): void
    {
        $event = new SyncEntityChanged(
            userId: 'user-abc', entityType: 'note', entityId: 'note-1',
            operation: 'update', data: ['title' => 'test'], vectorClock: ['d1' => 2],
            deviceId: 'device-x', timestamp: '2026-01-01T00:00:00Z',
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    // ── SyncShadow model ─────────────────────────

    public function test_sync_shadow_fillable(): void
    {
        $shadow = new \App\Models\SyncShadow();
        $fillable = $shadow->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('device_id', $fillable);
        $this->assertContains('entity_type', $fillable);
        $this->assertContains('entity_id', $fillable);
        $this->assertContains('shadow_data', $fillable);
        $this->assertContains('shadow_at', $fillable);
    }

    public function test_sync_shadow_has_no_timestamps(): void
    {
        $shadow = new \App\Models\SyncShadow();
        $this->assertFalse($shadow->usesTimestamps());
    }

    public function test_sync_shadow_casts(): void
    {
        $shadow = new \App\Models\SyncShadow();
        $casts = $shadow->getCasts();

        $this->assertSame('array', $casts['shadow_data']);
        $this->assertSame('datetime', $casts['shadow_at']);
    }
}
