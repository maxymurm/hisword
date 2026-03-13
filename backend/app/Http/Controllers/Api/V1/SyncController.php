<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends BaseApiController
{
    public function __construct(
        protected SyncService $syncService,
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
}
