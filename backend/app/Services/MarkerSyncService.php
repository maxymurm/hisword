<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SyncOperation;
use App\Events\MarkerChanged;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\SyncLog;
use App\Models\SyncShadow;
use App\Models\User;
use App\Support\VectorClock;
use Illuminate\Support\Carbon;

/**
 * Marker-specific sync service.
 *
 * Maps the androidbible unified marker format (kind: 0=bookmark, 1=note, 2=highlight)
 * to the corresponding HisWord models, enabling sync from legacy mobile clients.
 */
class MarkerSyncService
{
    /** Map marker kind to entity type and model class. */
    private const KIND_MAP = [
        0 => ['entity_type' => 'bookmark', 'model' => Bookmark::class],
        1 => ['entity_type' => 'note',     'model' => Note::class],
        2 => ['entity_type' => 'highlight', 'model' => Highlight::class],
    ];

    /**
     * Push marker changes from a device.
     *
     * @param array $markers Array of marker changes with kind, gid, operation, data, updated_at
     * @return array{applied: int, conflicts: int, errors: array}
     */
    public function pushMarkers(User $user, string $deviceId, array $markers): array
    {
        $applied = 0;
        $conflicts = 0;
        $errors = [];

        foreach ($markers as $marker) {
            try {
                $kind = $marker['kind'];
                $mapping = self::KIND_MAP[$kind] ?? null;

                if (!$mapping) {
                    $errors[] = [
                        'gid' => $marker['gid'] ?? 'unknown',
                        'error' => "Unknown marker kind: {$kind}",
                    ];
                    continue;
                }

                $entityData = $this->mapMarkerToEntity($kind, $marker['data'] ?? []);
                $operation = SyncOperation::from($marker['operation']);
                $updatedAt = isset($marker['updated_at']) ? Carbon::parse($marker['updated_at']) : now();

                $result = $this->applyMarkerChange(
                    $mapping['model'],
                    $mapping['entity_type'],
                    $user,
                    $deviceId,
                    $marker['gid'],
                    $operation,
                    $entityData,
                    $updatedAt,
                );

                if ($result === 'applied') {
                    $applied++;
                } elseif ($result === 'conflict_resolved') {
                    $applied++;
                    $conflicts++;
                }

                if ($result === 'applied' || $result === 'conflict_resolved') {
                    MarkerChanged::dispatch(
                        userId: $user->id,
                        kind: $kind,
                        gid: $marker['gid'],
                        operation: $marker['operation'],
                        data: $entityData,
                        deviceId: $deviceId,
                        timestamp: now()->toIso8601String(),
                    );
                }
            } catch (\Throwable $e) {
                $errors[] = [
                    'gid' => $marker['gid'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return compact('applied', 'conflicts', 'errors');
    }

    /**
     * Pull marker changes since last sync.
     *
     * @return array{markers: array, server_time: string, has_more: bool}
     */
    public function pullMarkers(User $user, string $deviceId, ?string $lastSyncAt): array
    {
        $since = $lastSyncAt ? Carbon::parse($lastSyncAt) : Carbon::createFromTimestamp(0);
        $maxBatch = config('sync.max_batch_size', 500);
        $entityTypes = ['bookmark', 'note', 'highlight'];

        $query = SyncLog::where('user_id', $user->id)
            ->where('synced_at', '>', $since)
            ->where('device_id', '!=', $deviceId)
            ->whereIn('entity_type', $entityTypes)
            ->orderBy('synced_at');

        $total = $query->count();
        $logs = $query->limit($maxBatch)->get();

        $markers = $logs->map(function (SyncLog $log) {
            $kind = $this->entityTypeToKind($log->entity_type);
            $data = $log->payload;

            if ($log->operation !== SyncOperation::Delete) {
                $modelClass = self::KIND_MAP[$kind]['model'];
                $entity = $modelClass::withDeleted()->find($log->entity_id);
                if ($entity) {
                    $data = $this->mapEntityToMarker($kind, $entity->toArray());
                }
            }

            return [
                'kind' => $kind,
                'gid' => $log->entity_id,
                'operation' => $log->operation->value,
                'data' => $data,
                'updated_at' => $log->synced_at->toIso8601String(),
            ];
        })->toArray();

        return [
            'markers' => $markers,
            'server_time' => now()->toIso8601String(),
            'has_more' => $total > $maxBatch,
        ];
    }

    /**
     * Apply a single marker change with LWW on updated_at.
     */
    private function applyMarkerChange(
        string $modelClass,
        string $entityType,
        User $user,
        string $deviceId,
        string $entityId,
        SyncOperation $operation,
        array $data,
        Carbon $updatedAt,
    ): string {
        $result = match ($operation) {
            SyncOperation::Create => $this->handleCreate($modelClass, $user, $entityId, $data, $updatedAt),
            SyncOperation::Update => $this->handleUpdate($modelClass, $user, $entityId, $data, $updatedAt),
            SyncOperation::Delete => $this->handleDelete($modelClass, $entityId, $updatedAt),
        };

        // Log it
        SyncLog::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'operation' => $operation,
            'payload' => $data,
            'vector_clock' => [],
        ]);

        // Update shadow
        if ($operation !== SyncOperation::Delete) {
            SyncShadow::updateOrCreate(
                ['device_id' => $deviceId, 'entity_type' => $entityType, 'entity_id' => $entityId],
                ['user_id' => $user->id, 'shadow_data' => $data, 'shadow_at' => now()],
            );
        } else {
            SyncShadow::where('device_id', $deviceId)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->delete();
        }

        return $result;
    }

    private function handleCreate(string $modelClass, User $user, string $entityId, array $data, Carbon $updatedAt): string
    {
        $existing = $modelClass::withDeleted()->find($entityId);

        if ($existing) {
            if ($existing->is_deleted ?? false) {
                $data['is_deleted'] = false;
                $data['deleted_at'] = null;
                $existing->update($data);
                return 'conflict_resolved';
            }
            // Already exists — LWW
            if ($updatedAt->greaterThan($existing->updated_at)) {
                unset($data['id'], $data['user_id']);
                $existing->update($data);
            }
            return 'conflict_resolved';
        }

        $entity = new $modelClass();
        $entity->id = $entityId;
        $entity->user_id = $user->id;
        $entity->fill($data);
        $entity->save();

        return 'applied';
    }

    private function handleUpdate(string $modelClass, User $user, string $entityId, array $data, Carbon $updatedAt): string
    {
        $existing = $modelClass::withDeleted()->find($entityId);

        if (!$existing) {
            return $this->handleCreate($modelClass, $user, $entityId, $data, $updatedAt);
        }

        if ($existing->is_deleted ?? false) {
            return 'conflict_resolved'; // Delete wins
        }

        // LWW on updated_at
        if ($updatedAt->greaterThan($existing->updated_at)) {
            unset($data['id'], $data['user_id']);
            $existing->update($data);
            return 'applied';
        }

        return 'conflict_resolved'; // Server is newer
    }

    private function handleDelete(string $modelClass, string $entityId, Carbon $updatedAt): string
    {
        $existing = $modelClass::withDeleted()->find($entityId);

        if (!$existing || ($existing->is_deleted ?? false)) {
            return 'applied';
        }

        $existing->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        return 'applied';
    }

    /**
     * Map unified marker data to entity-specific fields.
     */
    private function mapMarkerToEntity(int $kind, array $data): array
    {
        $base = [
            'book_osis_id' => $data['book_osis_id'] ?? $data['bookOsisId'] ?? null,
            'chapter_number' => $data['chapter'] ?? $data['chapter_number'] ?? null,
            'module_key' => $data['module_key'] ?? $data['moduleKey'] ?? null,
        ];

        return match ($kind) {
            0 => array_merge($base, [ // bookmark
                'verse_start' => $data['verse'] ?? $data['verse_start'] ?? 1,
                'verse_end' => $data['verse_end'] ?? $data['verse'] ?? $data['verse_start'] ?? 1,
                'label' => $data['caption'] ?? $data['label'] ?? '',
            ]),
            1 => array_merge($base, [ // note
                'verse_start' => $data['verse'] ?? $data['verse_start'] ?? 1,
                'verse_end' => $data['verse_end'] ?? $data['verse'] ?? $data['verse_start'] ?? 1,
                'title' => $data['caption'] ?? $data['title'] ?? '',
                'content' => $data['content'] ?? '',
            ]),
            2 => array_merge($base, [ // highlight
                'verse_number' => $data['verse'] ?? $data['verse_number'] ?? 1,
                'color' => $data['color'] ?? 'yellow',
            ]),
            default => $base,
        };
    }

    /**
     * Map entity back to unified marker format.
     */
    private function mapEntityToMarker(int $kind, array $entity): array
    {
        $base = [
            'book_osis_id' => $entity['book_osis_id'] ?? null,
            'chapter' => $entity['chapter_number'] ?? null,
            'module_key' => $entity['module_key'] ?? null,
        ];

        return match ($kind) {
            0 => array_merge($base, [
                'verse' => $entity['verse_start'] ?? 1,
                'verse_end' => $entity['verse_end'] ?? $entity['verse_start'] ?? 1,
                'caption' => $entity['label'] ?? '',
            ]),
            1 => array_merge($base, [
                'verse' => $entity['verse_start'] ?? 1,
                'caption' => $entity['title'] ?? '',
                'content' => $entity['content'] ?? '',
            ]),
            2 => array_merge($base, [
                'verse' => $entity['verse_number'] ?? 1,
                'color' => $entity['color'] ?? 'yellow',
            ]),
            default => $base,
        };
    }

    private function entityTypeToKind(string $entityType): int
    {
        return match ($entityType) {
            'bookmark' => 0,
            'note' => 1,
            'highlight' => 2,
            default => throw new \InvalidArgumentException("Unmapped entity type: {$entityType}"),
        };
    }
}
