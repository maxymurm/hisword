<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for reading plan progress updates.
 */
class PlanProgressChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly string $planId,
        public readonly int $dayNumber,
        public readonly bool $completed,
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
        return 'plan.progress.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'plan_id' => $this->planId,
            'day_number' => $this->dayNumber,
            'completed' => $this->completed,
            'device_id' => $this->deviceId,
            'timestamp' => $this->timestamp,
        ];
    }
}
