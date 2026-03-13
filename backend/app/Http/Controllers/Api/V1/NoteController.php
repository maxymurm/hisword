<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notes();

        if ($book = $request->query('book_osis_id')) {
            $query->where('book_osis_id', $book);
        }

        return $this->paginated($query->orderBy('created_at', 'desc'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_osis_id' => ['required', 'string', 'max:20'],
            'chapter_number' => ['required', 'integer', 'min:1'],
            'verse_start' => ['required', 'integer', 'min:1'],
            'verse_end' => ['nullable', 'integer', 'min:1'],
            'module_key' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'content_format' => ['sometimes', 'string', 'in:markdown,html'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $note = $request->user()->notes()->create($validated);

        return $this->success($note->fresh(), 'Note created', 201);
    }

    public function show(Request $request, string $note): JsonResponse
    {
        return $this->success($request->user()->notes()->findOrFail($note));
    }

    public function update(Request $request, string $note): JsonResponse
    {
        $n = $request->user()->notes()->findOrFail($note);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'content' => ['sometimes', 'string'],
            'content_format' => ['sometimes', 'string', 'in:markdown,html'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $n->update($validated);

        return $this->success($n->fresh(), 'Note updated');
    }

    public function destroy(Request $request, string $note): JsonResponse
    {
        $n = $request->user()->notes()->findOrFail($note);
        $n->syncDelete();

        return $this->success(null, 'Note deleted');
    }
}
