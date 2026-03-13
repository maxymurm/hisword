<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\HighlightColor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HighlightController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->highlights();

        if ($color = $request->query('color')) {
            $query->where('color', $color);
        }
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
            'verse_number' => ['required', 'integer', 'min:1'],
            'color' => ['required', Rule::enum(HighlightColor::class)],
            'module_key' => ['nullable', 'string', 'max:50'],
            'text_range_start' => ['nullable', 'integer'],
            'text_range_end' => ['nullable', 'integer'],
        ]);

        $highlight = $request->user()->highlights()->create($validated);

        return $this->success($highlight, 'Highlight created', 201);
    }

    public function show(Request $request, string $highlight): JsonResponse
    {
        return $this->success($request->user()->highlights()->findOrFail($highlight));
    }

    public function update(Request $request, string $highlight): JsonResponse
    {
        $h = $request->user()->highlights()->findOrFail($highlight);

        $validated = $request->validate([
            'color' => ['sometimes', Rule::enum(HighlightColor::class)],
            'text_range_start' => ['nullable', 'integer'],
            'text_range_end' => ['nullable', 'integer'],
        ]);

        $h->update($validated);

        return $this->success($h->fresh(), 'Highlight updated');
    }

    public function destroy(Request $request, string $highlight): JsonResponse
    {
        $h = $request->user()->highlights()->findOrFail($highlight);
        $h->syncDelete();

        return $this->success(null, 'Highlight deleted');
    }
}
