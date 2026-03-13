<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event fired after a sync push completes, summarizing all changes.
 *
 * Allows clients to decide whether to do a full pull or apply individual changes.
 */
class SyncBatchCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $userId     Owner of the entities
     * @param  string  $deviceId   Originating device
     * @param  int     $applied    Number of changes applied
     * @param  int     $conflicts  Number of conflicts resolved
     * @param  string  $timestamp  ISO-8601 server time after batch
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $deviceId,
        public readonly int $applied,
        public readonly int $conflicts,
        public readonly string $timestamp,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sync.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sync.batch.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'applied' => $this->applied,
            'conflicts' => $this->conflicts,
            'timestamp' => $this->timestamp,
        ];
    }
}
