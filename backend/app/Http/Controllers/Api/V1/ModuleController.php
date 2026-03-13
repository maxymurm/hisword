<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Module::query();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($language = $request->query('language')) {
            $query->where('language', $language);
        }
        if ($engine = $request->query('engine')) {
            $query->where('engine', $engine);
        }
        if ($request->has('installed')) {
            $query->where('is_installed', $request->boolean('installed'));
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->orderBy('name'));
    }

    public function show(string $module): JsonResponse
    {
        $mod = Module::where('key', $module)->orWhere('id', $module)->firstOrFail();

        return $this->success($mod->load('books'));
    }

    /**
     * List all available (not yet installed) modules.
     */
    public function available(Request $request): JsonResponse
    {
        $query = Module::where('is_installed', false);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($language = $request->query('language')) {
            $query->where('language', $language);
        }
        if ($engine = $request->query('engine')) {
            $query->where('engine', $engine);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->orderBy('name'));
    }

    /**
     * Mark a module as installed (triggered after device-side download).
     */
    public function install(string $module): JsonResponse
    {
        $mod = Module::where('key', $module)->orWhere('id', $module)->firstOrFail();

        if ($mod->is_installed) {
            return $this->success($mod, 'Module already installed');
        }

        $mod->update(['is_installed' => true]);

        return $this->success($mod->fresh(), 'Module installed', 201);
    }

    /**
     * Mark a module as uninstalled.
     */
    public function uninstall(string $module): JsonResponse
    {
        $mod = Module::where('key', $module)->orWhere('id', $module)->firstOrFail();

        $mod->update(['is_installed' => false]);

        return $this->success($mod->fresh(), 'Module uninstalled');
    }
}
