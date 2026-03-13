<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Bookmark;
use App\Models\BookmarkFolder;
use App\Models\Highlight;
use App\Models\History;
use App\Models\Note;
use App\Models\Pin;
use App\Models\ReadingPlanProgress;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DataExportController extends BaseApiController
{
    public const SCHEMA_VERSION = '1.0.0';
    public const APP_NAME = 'HisWord';

    /**
     * Exportable entity types mapped to their model classes.
     */
    protected array $entityMap = [
        'bookmarks' => Bookmark::class,
        'bookmark_folders' => BookmarkFolder::class,
        'highlights' => Highlight::class,
        'notes' => Note::class,
        'pins' => Pin::class,
        'history' => History::class,
        'settings' => UserPreference::class,
        'reading_plan_progress' => ReadingPlanProgress::class,
    ];

    /**
     * POST /export — Generate JSON export of user data.
     *
     * Body:
     *  - types (array, optional): Which entity types to include. Default: all.
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', 'in:' . implode(',', array_keys($this->entityMap))],
        ]);

        $types = $validated['types'] ?? array_keys($this->entityMap);
        $user = $request->user();
        $data = [];
        $counts = [];

        foreach ($types as $type) {
            $modelClass = $this->entityMap[$type];
            $query = $modelClass::where('user_id', $user->id);

            // For syncable models, include only non-deleted records
            if (method_exists($modelClass, 'scopeWithDeleted')) {
                // The global scope already excludes deleted, so this works
            }

            $records = $query->get()->toArray();
            $data[$type] = $records;
            $counts[$type] = count($records);
        }

        $export = [
            'app' => self::APP_NAME,
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => now()->toIso8601String(),
            'user_id' => $user->id,
            'user_email' => $user->email,
            'counts' => $counts,
            'data' => $data,
        ];

        return $this->success($export, 'Export generated');
    }

    /**
     * POST /export/preview — Preview what an import file contains.
     *
     * Body:
     *  - export_data (object, required): The JSON export data.
     */
    public function importPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'export_data' => ['required', 'array'],
            'export_data.schema_version' => ['required', 'string'],
            'export_data.data' => ['required', 'array'],
        ]);

        $exportData = $validated['export_data'];
        $user = $request->user();

        // Validate schema version
        if (version_compare($exportData['schema_version'], self::SCHEMA_VERSION, '>')) {
            return $this->error(
                'Export file is from a newer version ('.$exportData['schema_version'].').'
                .' Please update the app to import this file.',
                422,
            );
        }

        $preview = [];
        foreach ($exportData['data'] as $type => $records) {
            if (!isset($this->entityMap[$type])) {
                continue;
            }

            $modelClass = $this->entityMap[$type];
            $existingIds = $modelClass::where('user_id', $user->id)
                ->pluck('id')
                ->toArray();

            $incoming = collect($records);
            $newCount = $incoming->filter(fn ($r) => !in_array($r['id'] ?? '', $existingIds))->count();
            $conflictCount = $incoming->filter(fn ($r) => in_array($r['id'] ?? '', $existingIds))->count();

            $preview[$type] = [
                'total' => count($records),
                'new' => $newCount,
                'conflicts' => $conflictCount,
            ];
        }

        return $this->success([
            'schema_version' => $exportData['schema_version'],
            'exported_at' => $exportData['exported_at'] ?? null,
            'source_email' => $exportData['user_email'] ?? null,
            'preview' => $preview,
        ]);
    }

    /**
     * POST /import — Import data from an export file.
     *
     * Body:
     *  - export_data (object, required): The JSON export data.
     *  - conflict_strategy (string): "skip" | "overwrite" | "merge". Default: skip.
     *  - types (array, optional): Which types to import. Default: all found in file.
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'export_data' => ['required', 'array'],
            'export_data.schema_version' => ['required', 'string'],
            'export_data.data' => ['required', 'array'],
            'conflict_strategy' => ['sometimes', 'string', 'in:skip,overwrite,merge'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', 'in:' . implode(',', array_keys($this->entityMap))],
        ]);

        $exportData = $validated['export_data'];
        $strategy = $validated['conflict_strategy'] ?? 'skip';
        $types = $validated['types'] ?? array_keys($exportData['data']);
        $user = $request->user();

        // Validate schema version
        if (version_compare($exportData['schema_version'], self::SCHEMA_VERSION, '>')) {
            return $this->error(
                'Export file is from a newer version. Please update the app.',
                422,
            );
        }

        $results = [];
        // Map old IDs to new IDs for cross-user imports (FK remapping)
        $idMap = [];

        DB::beginTransaction();
        try {
            // Process bookmark_folders first so FK references work for bookmarks
            $orderedTypes = $this->orderTypesForImport($types);

            foreach ($orderedTypes as $type) {
                if (!isset($exportData['data'][$type]) || !isset($this->entityMap[$type])) {
                    continue;
                }

                $records = $exportData['data'][$type];
                $modelClass = $this->entityMap[$type];

                $imported = 0;
                $skipped = 0;
                $overwritten = 0;

                foreach ($records as $record) {
                    // Strip server-specific fields
                    unset($record['created_at'], $record['updated_at'], $record['deleted_at']);

                    $originalId = $record['id'] ?? null;
                    if (!$originalId) {
                        $skipped++;
                        continue;
                    }

                    // Force user_id to current user
                    $record['user_id'] = $user->id;

                    // Remap FK references using ID map
                    if (isset($record['folder_id']) && isset($idMap[$record['folder_id']])) {
                        $record['folder_id'] = $idMap[$record['folder_id']];
                    }
                    if (isset($record['parent_id']) && isset($idMap[$record['parent_id']])) {
                        $record['parent_id'] = $idMap[$record['parent_id']];
                    }
                    if (isset($record['plan_id']) && isset($idMap[$record['plan_id']])) {
                        $record['plan_id'] = $idMap[$record['plan_id']];
                    }

                    // Check for existing record owned by this user
                    $existing = null;
                    $query = $modelClass::where('user_id', $user->id);
                    if (method_exists($modelClass, 'scopeWithDeleted')) {
                        $query = $modelClass::withDeleted()->where('user_id', $user->id);
                    }
                    $existing = $query->where('id', $originalId)->first();

                    if ($existing) {
                        switch ($strategy) {
                            case 'skip':
                                $skipped++;
                                break;

                            case 'overwrite':
                                unset($record['id']);
                                $existing->fill($record);
                                $existing->save();
                                $overwritten++;
                                break;

                            case 'merge':
                                $fillable = $existing->getFillable();
                                $changed = false;
                                foreach ($fillable as $field) {
                                    if (
                                        isset($record[$field])
                                        && (is_null($existing->$field) || $existing->$field === '' || $existing->$field === [])
                                    ) {
                                        $existing->$field = $record[$field];
                                        $changed = true;
                                    }
                                }
                                if ($changed) {
                                    $existing->save();
                                    $overwritten++;
                                } else {
                                    $skipped++;
                                }
                                break;
                        }
                    } else {
                        // Create new — let model generate UUID if original already exists globally
                        unset($record['id']);
                        $entity = new $modelClass();
                        $entity->fill($record);
                        $entity->save();

                        // Track ID mapping for FK remapping
                        $idMap[$originalId] = $entity->id;
                        $imported++;
                    }
                }

                $results[$type] = [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'overwritten' => $overwritten,
                    'total' => count($records),
                ];
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->error('Import failed: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'results' => $results,
            'conflict_strategy' => $strategy,
        ], 'Import completed');
    }

    /**
     * Order entity types so parent entities are imported before children.
     * E.g., bookmark_folders before bookmarks (FK dependency).
     */
    private function orderTypesForImport(array $types): array
    {
        $priority = [
            'bookmark_folders' => 0,
            'settings' => 1,
            'bookmarks' => 2,
            'highlights' => 3,
            'notes' => 4,
            'pins' => 5,
            'history' => 6,
            'reading_plan_progress' => 7,
        ];

        usort($types, fn ($a, $b) => ($priority[$a] ?? 99) - ($priority[$b] ?? 99));

        return $types;
    }
}
