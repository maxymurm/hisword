<?php

namespace App\Services;

use App\Enums\SyncOperation;
use App\Events\SyncBatchCompleted;
use App\Events\SyncEntityChanged;
use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\Highlight;
use App\Models\History;
use App\Models\Note;
use App\Models\Pin;
use App\Models\SyncLog;
use App\Models\User;
use App\Models\UserPreference;
use App\Support\VectorClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SyncService
{
    /**
     * Map entity types to their model classes.
     */
    protected array $entityMap = [
        'bookmark' => Bookmark::class,
        'bookmark_folder' => BookmarkFolder::class,
        'highlight' => Highlight::class,
        'note' => Note::class,
        'pin' => Pin::class,
        'history' => History::class,
        'user_preference' => UserPreference::class,
    ];

    /**
     * Process incoming changes from a device (push).
     *
     * @return array{applied: int, conflicts: int, errors: array}
     */
    public function push(User $user, string $deviceId, array $changes): array
    {
        $applied = 0;
        $conflicts = 0;
        $errors = [];

        foreach ($changes as $change) {
            try {
                $result = $this->applyChange($user, $deviceId, $change);

                if ($result === 'applied') {
                    $applied++;
                } elseif ($result === 'conflict_resolved') {
                    $applied++;
                    $conflicts++;
                }

                // Broadcast individual change to other devices
                if ($result === 'applied' || $result === 'conflict_resolved') {
                    $this->broadcastChange($user, $deviceId, $change);
                }
            } catch (\Throwable $e) {
                $errors[] = [
                    'entity_type' => $change['entity_type'] ?? 'unknown',
                    'entity_id' => $change['entity_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Update device last_sync_at
        $user->devices()
            ->where('device_id', $deviceId)
            ->update(['last_sync_at' => now()]);

        // Broadcast batch completion summary
        if ($applied > 0) {
            SyncBatchCompleted::dispatch(
                userId: $user->id,
                deviceId: $deviceId,
                applied: $applied,
                conflicts: $conflicts,
                timestamp: now()->toIso8601String(),
            );
        }

        return compact('applied', 'conflicts', 'errors');
    }

    /**
     * Retrieve changes since last sync for a device (pull).
     *
     * @return array{changes: array, server_time: string, has_more: bool}
     */
    public function pull(User $user, string $deviceId, ?string $lastSyncAt, array $entityTypes = []): array
    {
        $since = $lastSyncAt ? Carbon::parse($lastSyncAt) : Carbon::createFromTimestamp(0);
        $maxBatch = config('sync.max_batch_size', 500);

        $query = SyncLog::where('user_id', $user->id)
            ->where('synced_at', '>', $since)
            ->where('device_id', '!=', $deviceId) // Don't send back own changes
            ->orderBy('synced_at');

        if (!empty($entityTypes)) {
            $query->whereIn('entity_type', $entityTypes);
        }

        $total = $query->count();
        $logs = $query->limit($maxBatch)->get();

        $changes = $logs->map(function (SyncLog $log) {
            // Fetch current entity state for creates/updates
            $data = null;
            if ($log->operation !== SyncOperation::Delete && isset($this->entityMap[$log->entity_type])) {
                $modelClass = $this->entityMap[$log->entity_type];
                $entity = $modelClass::withDeleted()->find($log->entity_id);
                $data = $entity?->toArray();
            }

            return [
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'operation' => $log->operation->value,
                'data' => $data ?? $log->payload,
                'vector_clock' => $log->vector_clock,
                'timestamp' => $log->synced_at->toIso8601String(),
            ];
        })->toArray();

        return [
            'changes' => $changes,
            'server_time' => now()->toIso8601String(),
            'has_more' => $total > $maxBatch,
        ];
    }

    /**
     * Apply a single change with LWW conflict resolution.
     */
    protected function applyChange(User $user, string $deviceId, array $change): string
    {
        $entityType = $change['entity_type'];
        $entityId = $change['entity_id'];
        $operation = SyncOperation::from($change['operation']);
        $data = $change['data'] ?? [];
        $incomingClock = $change['vector_clock'] ?? [];
        $timestamp = isset($change['timestamp']) ? Carbon::parse($change['timestamp']) : now();

        if (!isset($this->entityMap[$entityType])) {
            throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
        }

        $modelClass = $this->entityMap[$entityType];
        $result = 'applied';

        switch ($operation) {
            case SyncOperation::Create:
                $result = $this->handleCreate($modelClass, $user, $entityId, $data, $incomingClock);
                break;

            case SyncOperation::Update:
                $result = $this->handleUpdate($modelClass, $user, $entityId, $data, $incomingClock, $timestamp);
                break;

            case SyncOperation::Delete:
                $result = $this->handleDelete($modelClass, $user, $entityId, $incomingClock);
                break;
        }

        // Log the sync operation
        SyncLog::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'operation' => $operation,
            'payload' => $data,
            'vector_clock' => $incomingClock,
        ]);

        return $result;
    }

    protected function handleCreate(string $modelClass, User $user, string $entityId, array $data, array $clock): string
    {
        // Check if already exists (from another device)
        $existing = $modelClass::withDeleted()->find($entityId);

        if ($existing) {
            // If it was deleted, "un-delete" with new data (create wins for un-deleted)
            if ($existing->is_deleted ?? false) {
                $data['is_deleted'] = false;
                $data['deleted_at'] = null;
                $data['vector_clock'] = $this->mergeClock($existing->vector_clock ?? [], $clock);
                $existing->update($data);
                return 'conflict_resolved';
            }
            // Otherwise it already exists — merge clocks
            $existing->update(['vector_clock' => $this->mergeClock($existing->vector_clock ?? [], $clock)]);
            return 'conflict_resolved';
        }

        // Create new — force the ID from the client
        $entity = new $modelClass();
        $entity->id = $entityId;
        $entity->user_id = $user->id;
        $entity->vector_clock = $clock;
        $entity->fill($data);
        $entity->save();

        return 'applied';
    }

    protected function handleUpdate(string $modelClass, User $user, string $entityId, array $data, array $clock, Carbon $timestamp): string
    {
        $existing = $modelClass::withDeleted()->find($entityId);

        if (!$existing) {
            // Entity doesn't exist — treat as create
            return $this->handleCreate($modelClass, $user, $entityId, $data, $clock);
        }

        // Delete always wins over update
        if ($existing->is_deleted ?? false) {
            return 'conflict_resolved';
        }

        // LWW: compare clocks
        $existingClock = $existing->vector_clock ?? [];

        if ($this->isClockNewer($clock, $existingClock)) {
            // Incoming is newer — apply update
            $data['vector_clock'] = $this->mergeClock($existingClock, $clock);
            unset($data['id'], $data['user_id']); // Don't overwrite identity fields
            $existing->update($data);
            return 'applied';
        }

        // Server is newer — merge clocks but keep server data
        $existing->update(['vector_clock' => $this->mergeClock($existingClock, $clock)]);
        return 'conflict_resolved';
    }

    protected function handleDelete(string $modelClass, User $user, string $entityId, array $clock): string
    {
        $existing = $modelClass::withDeleted()->find($entityId);

        if (!$existing) {
            return 'applied'; // Already gone
        }

        if ($existing->is_deleted ?? false) {
            return 'applied'; // Already deleted
        }

        // Delete wins — tombstone the record
        $existing->update([
            'is_deleted' => true,
            'deleted_at' => now(),
            'vector_clock' => $this->mergeClock($existing->vector_clock ?? [], $clock),
        ]);

        return 'applied';
    }

    /**
     * Merge two vector clocks (take max of each component).
     */
    protected function mergeClock(array $a, array $b): array
    {
        return VectorClock::fromArray($a)->merge(VectorClock::fromArray($b))->toArray();
    }

    /**
     * Determine if clock A is strictly newer than clock B (LWW).
     */
    protected function isClockNewer(array $a, array $b): bool
    {
        return VectorClock::fromArray($a)->isNewerThan(VectorClock::fromArray($b));
    }

    /**
     * Broadcast a single entity change to the user's other devices.
     */
    protected function broadcastChange(User $user, string $deviceId, array $change): void
    {
        SyncEntityChanged::dispatch(
            userId: $user->id,
            entityType: $change['entity_type'],
            entityId: $change['entity_id'],
            operation: $change['operation'],
            data: $change['data'] ?? null,
            vectorClock: $change['vector_clock'] ?? [],
            deviceId: $deviceId,
            timestamp: now()->toIso8601String(),
        );
    }
}
