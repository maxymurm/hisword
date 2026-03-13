<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\Sword\SwordManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DevotionalController extends Controller
{
    public function __construct(protected SwordManager $sword) {}

    public function index(): Response
    {
        $devotionals = Module::where('type', 'devotional')
            ->where('is_installed', true)
            ->orderBy('name')
            ->get(['key', 'name', 'description', 'language']);

        return Inertia::render('Devotionals/Index', [
            'devotionals' => $devotionals,
        ]);
    }

    public function show(Request $request, string $module): Response
    {
        $date = $request->query('date', now()->format('m.d'));
        $mod = Module::where('key', $module)->firstOrFail();

        $entry = null;
        try {
            $text = $this->sword->readRawEntry($module, $date);
            $entry = [
                'date' => $date,
                'text' => $text,
            ];
        } catch (\Throwable) {
            $entry = [
                'date' => $date,
                'text' => null,
            ];
        }

        return Inertia::render('Devotionals/Show', [
            'module' => $mod,
            'entry' => $entry,
            'today' => now()->format('m.d'),
        ]);
    }
}
