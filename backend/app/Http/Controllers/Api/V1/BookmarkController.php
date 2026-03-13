<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Bookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->bookmarks()->with('folder');

        if ($folder = $request->query('folder_id')) {
            $query->where('folder_id', $folder);
        }
        if ($book = $request->query('book_osis_id')) {
            $query->where('book_osis_id', $book);
        }

        return $this->paginated($query->orderBy('sort_order')->orderBy('created_at', 'desc'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'folder_id' => ['nullable', 'uuid', 'exists:bookmark_folders,id'],
            'book_osis_id' => ['required', 'string', 'max:20'],
            'chapter_number' => ['required', 'integer', 'min:1'],
            'verse_start' => ['required', 'integer', 'min:1'],
            'verse_end' => ['nullable', 'integer', 'min:1'],
            'module_key' => ['nullable', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $bookmark = $request->user()->bookmarks()->create($validated);

        return $this->success($bookmark->load('folder'), 'Bookmark created', 201);
    }

    public function show(Request $request, string $bookmark): JsonResponse
    {
        $bm = $request->user()->bookmarks()->with('folder')->findOrFail($bookmark);

        return $this->success($bm);
    }

    public function update(Request $request, string $bookmark): JsonResponse
    {
        $bm = $request->user()->bookmarks()->findOrFail($bookmark);

        $validated = $request->validate([
            'folder_id' => ['nullable', 'uuid', 'exists:bookmark_folders,id'],
            'book_osis_id' => ['sometimes', 'string', 'max:20'],
            'chapter_number' => ['sometimes', 'integer', 'min:1'],
            'verse_start' => ['sometimes', 'integer', 'min:1'],
            'verse_end' => ['nullable', 'integer'],
            'module_key' => ['nullable', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $bm->update($validated);

        return $this->success($bm->fresh()->load('folder'), 'Bookmark updated');
    }

    public function destroy(Request $request, string $bookmark): JsonResponse
    {
        $bm = $request->user()->bookmarks()->findOrFail($bookmark);
        $bm->syncDelete();

        return $this->success(null, 'Bookmark deleted');
    }
}
