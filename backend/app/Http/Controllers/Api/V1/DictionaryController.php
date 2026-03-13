<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ModuleType;
use App\Models\Module;
use App\Models\Verse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DictionaryController extends BaseApiController
{
    /**
     * List all dictionary/lexicon modules.
     *
     * GET /api/v1/dictionaries
     */
    public function index(): JsonResponse
    {
        $dictionaries = Module::where('type', ModuleType::Dictionary)
            ->select(['id', 'key', 'name', 'description', 'language', 'version', 'is_installed'])
            ->orderBy('name')
            ->get();

        return $this->success($dictionaries);
    }

    /**
     * Show a single dictionary module.
     *
     * GET /api/v1/dictionaries/{module}
     */
    public function show(Module $module): JsonResponse
    {
        if ($module->type !== ModuleType::Dictionary) {
            return $this->error('Module is not a dictionary', 404);
        }

        return $this->success($module);
    }

    /**
     * Lookup a dictionary entry by key.
     *
     * GET /api/v1/dictionaries/{module}/entry/{key}
     */
    public function entry(Module $module, string $key): JsonResponse
    {
        if ($module->type !== ModuleType::Dictionary) {
            return $this->error('Module is not a dictionary', 404);
        }

        // Dictionary entries stored as verses where book_osis_id or text fields contain key
        $entry = Verse::where('module_id', $module->id)
            ->where('book_osis_id', $key)
            ->first();

        if (!$entry) {
            return $this->error('Entry not found', 404);
        }

        return $this->success([
            'key' => $key,
            'text' => $entry->text_rendered ?? $entry->text_raw,
            'module' => [
                'id' => $module->id,
                'key' => $module->key,
                'name' => $module->name,
            ],
        ]);
    }

    /**
     * Browse all entries in a dictionary (paginated).
     *
     * GET /api/v1/dictionaries/{module}/entries
     */
    public function entries(Module $module, Request $request): JsonResponse
    {
        if ($module->type !== ModuleType::Dictionary) {
            return $this->error('Module is not a dictionary', 404);
        }

        $validated = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Verse::where('module_id', $module->id)
            ->orderBy('book_osis_id');

        // Search within dictionary keys
        if (isset($validated['q'])) {
            $likeOp = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where('book_osis_id', $likeOp, '%' . $validated['q'] . '%');
        }

        return $this->paginated(
            $query->select(['id', 'book_osis_id', 'text_raw']),
            $validated['per_page'] ?? 50,
        );
    }
}
