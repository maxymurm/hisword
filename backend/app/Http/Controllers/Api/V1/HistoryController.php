<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $history = $request->user()->history()
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return $this->success($history);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_osis_id' => ['required', 'string', 'max:20'],
            'chapter_number' => ['required', 'integer', 'min:1'],
            'verse_number' => ['nullable', 'integer', 'min:1'],
            'module_key' => ['required', 'string', 'max:50'],
            'scroll_position' => ['nullable', 'numeric'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['created_at'] = now();

        $entry = $request->user()->history()->create($validated);

        return $this->success($entry, 'History entry added', 201);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $request->user()->history()->update(['is_deleted' => true]);

        return $this->success(null, 'History cleared');
    }

    public function destroy(Request $request, string $history): JsonResponse
    {
        $entry = $request->user()->history()->findOrFail($history);
        $entry->update(['is_deleted' => true]);

        return $this->success(null, 'History entry deleted');
    }
}
