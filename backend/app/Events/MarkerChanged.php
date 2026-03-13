<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for marker changes (bookmark/note/highlight).
 *
 * Uses the unified marker format compatible with androidbible mobile clients.
 */
class MarkerChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly int $kind,         // 0=bookmark, 1=note, 2=highlight
        public readonly string $gid,        // UUID
        public readonly string $operation,  // create, update, delete
        public readonly ?array $data,
        public readonly string $deviceId,
        public readonly string $timestamp,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sync.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'marker.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'kind' => $this->kind,
            'gid' => $this->gid,
            'operation' => $this->operation,
            'data' => $this->data,
            'device_id' => $this->deviceId,
            'timestamp' => $this->timestamp,
        ];
    }
}
