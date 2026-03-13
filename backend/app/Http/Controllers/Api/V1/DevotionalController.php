<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ModuleType;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevotionalController extends BaseApiController
{
    /**
     * List available devotional modules.
     */
    public function index(): JsonResponse
    {
        $devotionals = Module::where('type', ModuleType::Devotional)
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'description', 'language', 'version']);

        return $this->success($devotionals);
    }

    /**
     * Today's devotional entry for a given module.
     */
    public function today(Request $request, string $module): JsonResponse
    {
        $mod = Module::where('type', ModuleType::Devotional)
            ->where(fn ($q) => $q->where('id', $module)->orWhere('key', $module))
            ->firstOrFail();

        $dateKey = now()->format('m.d');

        $entry = $this->getEntryForKey($mod, $dateKey);

        return $this->success([
            'module_id' => $mod->id,
            'module_key' => $mod->key,
            'module_name' => $mod->name,
            'date' => now()->toDateString(),
            'date_key' => $dateKey,
            'title' => $entry['title'] ?? $dateKey,
            'content' => $entry['content'] ?? '',
            'has_scripture_refs' => $entry['has_scripture_refs'] ?? false,
        ]);
    }

    /**
     * Devotional entry for a specific date.
     */
    public function forDate(Request $request, string $module, string $date): JsonResponse
    {
        $mod = Module::where('type', ModuleType::Devotional)
            ->where(fn ($q) => $q->where('id', $module)->orWhere('key', $module))
            ->firstOrFail();

        // Accept both YYYY-MM-DD and MM.DD formats
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $parsed = \Carbon\Carbon::parse($date);
            $dateKey = $parsed->format('m.d');
            $displayDate = $parsed->toDateString();
        } elseif (preg_match('/^\d{2}\.\d{2}$/', $date)) {
            $dateKey = $date;
            $displayDate = now()->year . '-' . str_replace('.', '-', $date);
        } else {
            return $this->error('Invalid date format. Use YYYY-MM-DD or MM.DD', 422);
        }

        $entry = $this->getEntryForKey($mod, $dateKey);

        return $this->success([
            'module_id' => $mod->id,
            'module_key' => $mod->key,
            'module_name' => $mod->name,
            'date' => $displayDate,
            'date_key' => $dateKey,
            'title' => $entry['title'] ?? $dateKey,
            'content' => $entry['content'] ?? '',
            'has_scripture_refs' => $entry['has_scripture_refs'] ?? false,
        ]);
    }

    /**
     * Get entry from the module's devotional content.
     *
     * In production, this would interface with the SWORD library to read
     * the GenBook/Daily module's content. For now, returns a placeholder.
     */
    private function getEntryForKey(Module $module, string $dateKey): array
    {
        // TODO: Integrate with SWORD library to read actual devotional content.
        // The SWORD module stores entries indexed by date key (MM.DD format).
        return [
            'title' => $this->formatDateTitle($dateKey),
            'content' => '<p>Devotional content for ' . $dateKey . ' will be available when module content is loaded.</p>',
            'has_scripture_refs' => false,
        ];
    }

    private function formatDateTitle(string $dateKey): string
    {
        $parts = explode('.', $dateKey);
        if (count($parts) !== 2) return $dateKey;

        $months = ['January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'];
        $monthIndex = intval($parts[0]) - 1;

        if ($monthIndex >= 0 && $monthIndex < 12) {
            return $months[$monthIndex] . ' ' . intval($parts[1]);
        }

        return $dateKey;
    }
}
