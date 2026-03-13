<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event fired when a sync entity is created, updated, or deleted.
 *
 * Sent immediately (ShouldBroadcastNow) to the user's private sync channel
 * so all connected devices receive real-time updates.
 */
class SyncEntityChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $userId       Owner of the entity
     * @param  string  $entityType   e.g. 'bookmark', 'highlight', 'note', 'pin'
     * @param  string  $entityId     UUID of the entity
     * @param  string  $operation    'create', 'update', or 'delete'
     * @param  array   $data         Entity data payload (null for deletes)
     * @param  array   $vectorClock  Merged vector clock after the operation
     * @param  string  $deviceId     Originating device (excluded from broadcast)
     * @param  string  $timestamp    ISO-8601 timestamp of the change
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $operation,
        public readonly ?array $data,
        public readonly array $vectorClock,
        public readonly string $deviceId,
        public readonly string $timestamp,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sync.{$this->userId}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sync.entity.changed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'operation' => $this->operation,
            'data' => $this->data,
            'vector_clock' => $this->vectorClock,
            'device_id' => $this->deviceId,
            'timestamp' => $this->timestamp,
        ];
    }
}
