<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PinController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $pins = $request->user()->pins()
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success($pins);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_osis_id' => ['required', 'string', 'max:20'],
            'chapter_number' => ['required', 'integer', 'min:1'],
            'verse_number' => ['required', 'integer', 'min:1'],
            'module_key' => ['required', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $pin = $request->user()->pins()->create($validated);

        return $this->success($pin, 'Pin created', 201);
    }

    public function show(Request $request, string $pin): JsonResponse
    {
        return $this->success($request->user()->pins()->findOrFail($pin));
    }

    public function update(Request $request, string $pin): JsonResponse
    {
        $p = $request->user()->pins()->findOrFail($pin);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $p->update($validated);

        return $this->success($p->fresh(), 'Pin updated');
    }

    public function destroy(Request $request, string $pin): JsonResponse
    {
        $p = $request->user()->pins()->findOrFail($pin);
        $p->syncDelete();

        return $this->success(null, 'Pin deleted');
    }
}
