<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BookmarkFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkFolderController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $folders = $request->user()->bookmarkFolders()
            ->with('children', 'bookmarks')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success($folders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['sometimes', 'string', 'max:7'],
            'parent_id' => ['nullable', 'uuid', 'exists:bookmark_folders,id'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $folder = $request->user()->bookmarkFolders()->create($validated);

        return $this->success($folder, 'Folder created', 201);
    }

    public function show(Request $request, string $folder): JsonResponse
    {
        $f = $request->user()->bookmarkFolders()->with('children', 'bookmarks')->findOrFail($folder);

        return $this->success($f);
    }

    public function update(Request $request, string $folder): JsonResponse
    {
        $f = $request->user()->bookmarkFolders()->findOrFail($folder);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', 'string', 'max:7'],
            'parent_id' => ['nullable', 'uuid', 'exists:bookmark_folders,id'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $f->update($validated);

        return $this->success($f->fresh(), 'Folder updated');
    }

    public function destroy(Request $request, string $folder): JsonResponse
    {
        $f = $request->user()->bookmarkFolders()->findOrFail($folder);
        $f->syncDelete();

        return $this->success(null, 'Folder deleted');
    }
}
