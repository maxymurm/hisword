<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CatalogController extends BaseApiController
{
    /**
     * List available YES2/Bintex Bible versions for download.
     *
     * GET /api/v1/catalog/versions?language=&search=
     */
    public function versions(Request $request): JsonResponse
    {
        $query = Module::where('engine', 'bintex');

        if ($language = $request->query('language')) {
            $query->where('language', $language);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $modules = $query->orderBy('language')
            ->orderBy('name')
            ->get([
                'id', 'key', 'name', 'description', 'language',
                'version', 'driver', 'file_size', 'is_installed',
            ]);

        return $this->success($modules);
    }

    /**
     * Download a YES2/Bintex module file.
     *
     * GET /api/v1/catalog/versions/{id}/download
     */
    public function download(string $id): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $module = Module::where('engine', 'bintex')
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('key', $id);
            })
            ->firstOrFail();

        if (!$module->data_path) {
            return $this->error('Module file not available', 404);
        }

        $disk = Storage::disk(config('bintex.module_disk', 'local'));
        $filePath = $module->data_path;

        if (!$disk->exists($filePath)) {
            return $this->error('Module file not found on disk', 404);
        }

        $filename = $module->key . '.' . ($module->driver ?? 'yes2');

        return $disk->download($filePath, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
