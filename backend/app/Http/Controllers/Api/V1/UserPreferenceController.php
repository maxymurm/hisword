<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends BaseApiController
{
    /**
     * Default values for all syncable settings.
     */
    public const DEFAULTS = [
        // Appearance
        'ui_theme_variant' => 'LIGHT',
        'reader_font_size' => 16,
        'reader_paragraph_mode' => false,

        // Reader features
        'reader_show_strongs' => false,
        'reader_show_morphology' => false,
        'reader_red_letter' => true,
        'reader_cross_references' => true,
        'reader_footnotes' => true,

        // Modules
        'default_bible_module' => null,
        'default_commentary_module' => null,
        'default_dictionary_module' => null,

        // Notifications
        'notification_verse_of_day' => true,
        'notification_reading_plan' => true,
        'notification_time' => '08:00',

        // Sync
        'sync_enabled' => true,
    ];

    /**
     * Settings that should NOT be synced across devices (device-specific).
     */
    public const LOCAL_ONLY_KEYS = [
        'download_path',
        'cache_size_mb',
        'wifi_only_downloads',
        'device_push_token',
    ];

    /**
     * Get all settings as a key-value map with categories.
     */
    public function index(Request $request): JsonResponse
    {
        $stored = $request->user()->preferences->pluck('value', 'key');

        // Merge defaults with stored values (stored takes precedence)
        $settings = collect(self::DEFAULTS)->map(function ($default, $key) use ($stored) {
            if ($stored->has($key)) {
                $val = $stored[$key];
                // Unwrap single-value arrays
                return is_array($val) && isset($val['value']) && count($val) === 1
                    ? $val['value']
                    : $val;
            }
            return $default;
        });

        // Add any extra stored keys not in defaults (e.g. custom settings)
        foreach ($stored as $key => $val) {
            if (!$settings->has($key)) {
                $settings[$key] = is_array($val) && isset($val['value']) && count($val) === 1
                    ? $val['value']
                    : $val;
            }
        }

        return $this->success([
            'settings' => $settings,
            'categories' => $this->categorize($settings),
            'local_only_keys' => self::LOCAL_ONLY_KEYS,
        ]);
    }

    /**
     * Update one or more settings. Accepts { "settings": { "key": value } }.
     * Only syncs non-local-only keys; ignores local-only keys silently.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $user = $request->user();
        $deviceId = $request->header('X-Device-Id', 'unknown');
        $updated = [];

        foreach ($validated['settings'] as $key => $value) {
            // Skip local-only settings — they shouldn't be stored server-side
            if (in_array($key, self::LOCAL_ONLY_KEYS, true)) {
                continue;
            }

            $wrapped = is_array($value) ? $value : ['value' => $value];

            $pref = $user->preferences()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $wrapped,
                    'vector_clock' => $this->incrementClock(
                        $user->preferences()->where('key', $key)->value('vector_clock') ?? [],
                        $deviceId,
                    ),
                    'updated_at' => now(),
                ],
            );

            $updated[$key] = $value;
        }

        return $this->success([
            'settings' => $updated,
            'updated_count' => count($updated),
        ], 'Settings updated');
    }

    /**
     * Reset all settings to defaults (delete stored preferences).
     */
    public function reset(Request $request): JsonResponse
    {
        $request->user()->preferences()->delete();

        return $this->success([
            'settings' => self::DEFAULTS,
        ], 'Settings reset to defaults');
    }

    /**
     * Get only the settings that have been modified (differ from defaults).
     */
    public function diff(Request $request): JsonResponse
    {
        $stored = $request->user()->preferences;

        $modified = $stored->mapWithKeys(function (UserPreference $pref) {
            $val = $pref->value;
            $unwrapped = is_array($val) && isset($val['value']) && count($val) === 1
                ? $val['value']
                : $val;

            $default = self::DEFAULTS[$pref->key] ?? null;

            if ($unwrapped != $default) {
                return [$pref->key => [
                    'current' => $unwrapped,
                    'default' => $default,
                    'updated_at' => $pref->updated_at?->toIso8601String(),
                ]];
            }

            return [];
        })->filter();

        return $this->success($modified);
    }

    /**
     * Organize settings into UI-friendly categories.
     */
    private function categorize($settings): array
    {
        $cats = [
            'appearance' => ['ui_theme_variant', 'reader_font_size', 'reader_paragraph_mode'],
            'reader' => ['reader_show_strongs', 'reader_show_morphology', 'reader_red_letter', 'reader_cross_references', 'reader_footnotes'],
            'modules' => ['default_bible_module', 'default_commentary_module', 'default_dictionary_module'],
            'notifications' => ['notification_verse_of_day', 'notification_reading_plan', 'notification_time'],
            'sync' => ['sync_enabled'],
        ];

        $result = [];
        foreach ($cats as $category => $keys) {
            $result[$category] = [];
            foreach ($keys as $key) {
                if ($settings->has($key)) {
                    $result[$category][$key] = $settings[$key];
                }
            }
        }

        return $result;
    }

    /**
     * Increment a vector clock for the given device.
     */
    private function incrementClock(array $clock, string $deviceId): array
    {
        $clock[$deviceId] = ($clock[$deviceId] ?? 0) + 1;
        return $clock;
    }
}
