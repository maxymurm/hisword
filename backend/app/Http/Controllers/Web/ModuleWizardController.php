<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleWizardController extends Controller
{
    public function index(Request $request): Response
    {
        $languages = Module::where('is_installed', false)
            ->whereNotNull('language')
            ->select('language')
            ->selectRaw('count(*) as count')
            ->groupBy('language')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['code' => $row->language, 'count' => $row->count]);

        $types = Module::where('is_installed', false)
            ->whereNotNull('type')
            ->select('type')
            ->selectRaw('count(*) as count')
            ->groupBy('type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['name' => $row->type, 'count' => $row->count]);

        return Inertia::render('ModuleWizard', [
            'languages' => $languages,
            'types' => $types,
        ]);
    }

    public function modules(Request $request)
    {
        $language = $request->query('language');
        $type = $request->query('type');

        $query = Module::where('is_installed', false);

        if ($language) {
            $query->where('language', $language);
        }
        if ($type) {
            $query->where('type', $type);
        }

        return response()->json(
            $query->orderBy('name')->get(['key', 'name', 'description', 'type', 'language', 'size'])
        );
    }
}
