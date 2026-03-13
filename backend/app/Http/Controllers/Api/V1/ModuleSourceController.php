<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ModuleSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleSourceController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(ModuleSource::orderBy('caption')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'caption' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:FTP,HTTP'],
            'server' => ['required', 'string', 'max:255'],
            'directory' => ['required', 'string', 'max:500'],
        ]);

        $source = ModuleSource::create([
            ...$validated,
            'is_active' => true,
        ]);

        return $this->success($source, 'Source added', 201);
    }

    public function show(string $source): JsonResponse
    {
        return $this->success(ModuleSource::findOrFail($source));
    }

    public function update(Request $request, string $source): JsonResponse
    {
        $validated = $request->validate([
            'caption' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:FTP,HTTP'],
            'server' => ['sometimes', 'string', 'max:255'],
            'directory' => ['sometimes', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model = ModuleSource::findOrFail($source);
        $model->update($validated);

        return $this->success($model->fresh(), 'Source updated');
    }

    public function destroy(string $source): JsonResponse
    {
        ModuleSource::findOrFail($source)->delete();

        return $this->success(null, 'Source removed');
    }
}
