<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\InstallModuleJob;
use App\Models\Module;
use App\Models\ModuleSource;
use App\Services\Sword\ModuleInstaller;
use App\Services\Sword\RepositoryBrowser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModuleController extends Controller
{
    public function __construct(
        protected ModuleInstaller $installer,
        protected RepositoryBrowser $browser,
    ) {}

    /**
     * Module library page — browse, install, remove modules.
     */
    public function index(Request $request): Response
    {
        $type = $request->query('type');
        $language = $request->query('language');
        $search = $request->query('search');
        $filter = $request->query('filter', 'all'); // all, installed, available
        $engine = $request->query('engine');

        $query = Module::query();

        if ($type) {
            $query->where('type', $type);
        }
        if ($language) {
            $query->where('language', $language);
        }
        if ($engine) {
            $query->where('engine', $engine);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($filter === 'installed') {
            $query->where('is_installed', true);
        } elseif ($filter === 'available') {
            $query->where('is_installed', false);
        }

        $modules = $query->orderByDesc('is_installed')
            ->orderByDesc('is_bundled')
            ->orderBy('name')
            ->paginate(24);

        // Get summary counts
        $counts = [
            'total' => Module::count(),
            'installed' => Module::where('is_installed', true)->count(),
            'available' => Module::where('is_installed', false)->count(),
        ];

        // Get available types and languages for filtering
        $types = Module::distinct()->pluck('type')->filter()->sort()->values();
        $languages = Module::distinct()->pluck('language')->filter()->sort()->values();

        // Get active progress states from cache
        $activeDownloads = $this->getActiveDownloads();

        return Inertia::render('Modules', [
            'modules' => $modules,
            'counts' => $counts,
            'types' => $types,
            'languages' => $languages,
            'filters' => [
                'type' => $type,
                'language' => $language,
                'search' => $search,
                'filter' => $filter,
                'engine' => $engine,
            ],
            'activeDownloads' => $activeDownloads,
            'sources' => ModuleSource::where('is_active', true)->get(),
        ]);
    }

    /**
     * Install a module — dispatches a background job and returns immediately.
     */
    public function install(Request $request, string $moduleKey): JsonResponse
    {
        $module = Module::where('key', $moduleKey)->first();

        if (!$module) {
            return response()->json(['error' => "Module '{$moduleKey}' not found."], 404);
        }

        if ($module->is_installed && !$request->boolean('force')) {
            return response()->json(['error' => "Module '{$moduleKey}' is already installed."], 409);
        }

        // Initialize progress state
        cache()->put("sword_progress_{$moduleKey}", [
            'module' => $moduleKey,
            'message' => 'Queued for installation...',
            'percent' => 0,
            'status' => 'queued',
            'timestamp' => now()->toISOString(),
        ], 300);

        // Check if queue driver is sync (dev mode) - run inline
        if (config('queue.default') === 'sync') {
            try {
                $this->installer->install($moduleKey, $request->boolean('force'), function (string $msg, int $pct) use ($moduleKey) {
                    cache()->put("sword_progress_{$moduleKey}", [
                        'module' => $moduleKey,
                        'message' => $msg,
                        'percent' => $pct,
                        'status' => $pct >= 100 ? 'completed' : 'in_progress',
                        'timestamp' => now()->toISOString(),
                    ], 300);
                });

                return response()->json([
                    'success' => true,
                    'message' => "{$moduleKey} installed successfully.",
                    'module' => $module->fresh(),
                ]);
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        // Dispatch background job
        InstallModuleJob::dispatch($moduleKey, $request->boolean('force'));

        return response()->json([
            'success' => true,
            'message' => "Installation of {$moduleKey} has been queued.",
            'polling_url' => route('modules.progress', $moduleKey),
        ]);
    }

    /**
     * Install a module from a bundled ZIP (for initial setup).
     */
    public function installBundled(Request $request): JsonResponse
    {
        $results = [];
        $bundled = config('bible.bundled_modules', []);
        $total = count($bundled);

        foreach ($bundled as $i => $moduleKey) {
            $module = Module::where('key', $moduleKey)->first();
            if ($module?->is_installed) {
                $results[$moduleKey] = ['status' => 'already_installed'];
                continue;
            }

            // Set initial progress
            cache()->put("sword_progress_{$moduleKey}", [
                'module' => $moduleKey,
                'message' => "Queued ({$i}/{$total})...",
                'percent' => 0,
                'status' => 'queued',
                'timestamp' => now()->toISOString(),
            ], 300);

            if (config('queue.default') === 'sync') {
                try {
                    $this->installer->install($moduleKey, true, function (string $msg, int $pct) use ($moduleKey) {
                        cache()->put("sword_progress_{$moduleKey}", [
                            'module' => $moduleKey,
                            'message' => $msg,
                            'percent' => $pct,
                            'status' => $pct >= 100 ? 'completed' : 'in_progress',
                            'timestamp' => now()->toISOString(),
                        ], 300);
                    });
                    $results[$moduleKey] = ['status' => 'installed'];
                } catch (\Throwable $e) {
                    $results[$moduleKey] = ['status' => 'error', 'message' => $e->getMessage()];
                }
            } else {
                InstallModuleJob::dispatch($moduleKey, true);
                $results[$moduleKey] = ['status' => 'queued'];
            }
        }

        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * Remove an installed module.
     */
    public function uninstall(Request $request, string $moduleKey): JsonResponse
    {
        try {
            $this->installer->remove($moduleKey, $request->boolean('keep_data'));

            return response()->json([
                'success' => true,
                'message' => "{$moduleKey} has been removed.",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * SSE endpoint — streams installation progress in real-time.
     * Mirrors HisWord's 50ms timer polling of PSStatusReporter.
     */
    public function progress(string $moduleKey): StreamedResponse
    {
        return response()->stream(function () use ($moduleKey) {
            $lastData = '';
            $maxIterations = 600; // 5 minutes at 500ms intervals
            $iteration = 0;

            while ($iteration < $maxIterations) {
                $progress = cache()->get("sword_progress_{$moduleKey}");

                if ($progress) {
                    $json = json_encode($progress);

                    // Only send if data changed
                    if ($json !== $lastData) {
                        echo "data: {$json}\n\n";
                        $lastData = $json;
                    }

                    // Check for terminal states
                    if (in_array($progress['status'] ?? '', ['completed', 'failed'])) {
                        // Send final event
                        echo "event: done\n";
                        echo "data: {$json}\n\n";
                        break;
                    }
                } else {
                    // No progress data yet — send heartbeat
                    echo "data: " . json_encode([
                        'module' => $moduleKey,
                        'message' => 'Waiting...',
                        'percent' => 0,
                        'status' => 'waiting',
                    ]) . "\n\n";
                }

                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                // Connection check
                if (connection_aborted()) {
                    break;
                }

                usleep(500_000); // 500ms polling interval
                $iteration++;
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Polling endpoint for progress (fallback when SSE not supported).
     */
    public function progressPoll(string $moduleKey): JsonResponse
    {
        $progress = cache()->get("sword_progress_{$moduleKey}");

        if (!$progress) {
            return response()->json([
                'module' => $moduleKey,
                'message' => 'No active installation.',
                'percent' => 0,
                'status' => 'idle',
            ]);
        }

        return response()->json($progress);
    }

    /**
     * Refresh module catalog from sources.
     */
    public function refreshSources(Request $request): JsonResponse
    {
        try {
            $result = $this->browser->refreshAll();

            return response()->json([
                'success' => true,
                'message' => "Refreshed {$result['refreshed']} sources. Found {$result['modules_found']} modules.",
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get module details.
     */
    public function show(string $moduleKey): JsonResponse
    {
        $module = Module::where('key', $moduleKey)->first();

        if (!$module) {
            return response()->json(['error' => 'Module not found.'], 404);
        }

        return response()->json($module);
    }

    /**
     * Get all active downloads from cache.
     */
    private function getActiveDownloads(): array
    {
        $downloads = [];

        // Check all cached progress keys
        $modules = Module::where('is_installed', false)->pluck('key');
        foreach ($modules as $key) {
            $progress = cache()->get("sword_progress_{$key}");
            if ($progress && !in_array($progress['status'], ['completed', 'idle'])) {
                $downloads[$key] = $progress;
            }
        }

        return $downloads;
    }
}
