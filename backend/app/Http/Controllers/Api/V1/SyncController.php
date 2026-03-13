<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\MarkerSyncService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends BaseApiController
{
    public function __construct(
        protected SyncService $syncService,
        protected MarkerSyncService $markerSyncService,
    ) {}

    /**
     * Push local changes to the server.
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
            'changes' => ['required', 'array', 'max:' . config('sync.max_batch_size', 500)],
            'changes.*.entity_type' => ['required', 'string', 'in:bookmark,bookmark_folder,highlight,note,pin,history,user_preference,reading_plan_progress'],
            'changes.*.entity_id' => ['required', 'uuid'],
            'changes.*.operation' => ['required', 'string', 'in:create,update,delete'],
            'changes.*.data' => ['sometimes', 'array'],
            'changes.*.vector_clock' => ['sometimes', 'array'],
            'changes.*.timestamp' => ['sometimes', 'date'],
        ]);

        $result = $this->syncService->push(
            $request->user(),
            $validated['device_id'],
            $validated['changes'],
        );

        return $this->success([
            'applied' => $result['applied'],
            'conflicts' => $result['conflicts'],
            'errors' => $result['errors'],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Pull server changes since last sync.
     */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
            'last_sync_at' => ['nullable', 'date'],
            'entity_types' => ['sometimes', 'array'],
            'entity_types.*' => ['string', 'in:bookmark,bookmark_folder,highlight,note,pin,history,user_preference,reading_plan_progress'],
        ]);

        $result = $this->syncService->pull(
            $request->user(),
            $validated['device_id'],
            $validated['last_sync_at'] ?? null,
            $validated['entity_types'] ?? [],
        );

        return $this->success($result);
    }

    /**
     * Push marker changes (unified androidbible format: kind 0=bookmark, 1=note, 2=highlight).
     */
    public function pushMarkers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
            'markers' => ['required', 'array', 'max:' . config('sync.max_batch_size', 500)],
            'markers.*.kind' => ['required', 'integer', 'in:0,1,2'],
            'markers.*.gid' => ['required', 'uuid'],
            'markers.*.operation' => ['required', 'string', 'in:create,update,delete'],
            'markers.*.data' => ['sometimes', 'array'],
            'markers.*.updated_at' => ['sometimes', 'date'],
        ]);

        $result = $this->markerSyncService->pushMarkers(
            $request->user(),
            $validated['device_id'],
            $validated['markers'],
        );

        return $this->success([
            'applied' => $result['applied'],
            'conflicts' => $result['conflicts'],
            'errors' => $result['errors'],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Pull marker changes since last sync.
     */
    public function pullMarkers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
            'last_sync_at' => ['nullable', 'date'],
        ]);

        $result = $this->markerSyncService->pullMarkers(
            $request->user(),
            $validated['device_id'],
            $validated['last_sync_at'] ?? null,
        );

        return $this->success($result);
    }
}
