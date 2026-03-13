<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ModuleType;
use App\Models\Module;
use App\Models\Verse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentaryController extends BaseApiController
{
    /**
     * List all commentary modules.
     *
     * GET /api/v1/commentaries
     */
    public function index(): JsonResponse
    {
        $commentaries = Module::where('type', ModuleType::Commentary)
            ->select(['id', 'key', 'name', 'description', 'language', 'version', 'is_installed'])
            ->orderBy('name')
            ->get();

        return $this->success($commentaries);
    }

    /**
     * Show a single commentary module.
     *
     * GET /api/v1/commentaries/{module}
     */
    public function show(Module $module): JsonResponse
    {
        if ($module->type !== ModuleType::Commentary) {
            return $this->error('Module is not a commentary', 404);
        }

        return $this->success($module);
    }

    /**
     * Get commentary entry for a specific verse or passage.
     *
     * GET /api/v1/commentaries/{module}/entry?book={osis}&chapter={n}&verse={n}
     */
    public function entry(Module $module, Request $request): JsonResponse
    {
        if ($module->type !== ModuleType::Commentary) {
            return $this->error('Module is not a commentary', 404);
        }

        $validated = $request->validate([
            'book' => ['required', 'string', 'max:20'],
            'chapter' => ['required', 'integer', 'min:1'],
            'verse' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Verse::where('module_id', $module->id)
            ->where('book_osis_id', $validated['book'])
            ->where('chapter_number', $validated['chapter']);

        if (isset($validated['verse'])) {
            $query->where('verse_number', $validated['verse']);
        }

        $entries = $query
            ->select(['id', 'book_osis_id', 'chapter_number', 'verse_number', 'text_raw', 'text_rendered'])
            ->orderBy('verse_number')
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'book_osis_id' => $entry->book_osis_id,
                'chapter' => $entry->chapter_number,
                'verse' => $entry->verse_number,
                'text' => $entry->text_rendered ?? $entry->text_raw,
                'reference' => sprintf(
                    '%s %d:%d',
                    config('bible.osis_to_name.' . $entry->book_osis_id, $entry->book_osis_id),
                    $entry->chapter_number,
                    $entry->verse_number,
                ),
            ]);

        return $this->success([
            'module' => [
                'id' => $module->id,
                'key' => $module->key,
                'name' => $module->name,
            ],
            'entries' => $entries,
        ]);
    }
}
