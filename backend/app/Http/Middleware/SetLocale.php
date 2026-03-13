<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detect and set the application locale from:
 * 1. Query parameter (?lang=xx)
 * 2. User preference (database)
 * 3. Session
 * 4. Accept-Language header
 * 5. Fallback to config default
 */
class SetLocale
{
    public const SUPPORTED_LOCALES = [
        'en', 'ar', 'cs', 'de', 'es', 'fr', 'it',
        'ja', 'ko', 'nl', 'pt', 'ru', 'sv', 'th',
        'uk', 'zh-Hans', 'zh-Hant',
    ];

    public const RTL_LOCALES = ['ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        // Share locale and direction with views/Inertia
        $isRtl = in_array($locale, self::RTL_LOCALES);

        // Load translations for the current locale
        $translations = $this->loadTranslations($locale);

        if (class_exists(\Inertia\Inertia::class)) {
            \Inertia\Inertia::share([
                'locale' => $locale,
                'dir' => $isRtl ? 'rtl' : 'ltr',
                'availableLocales' => self::SUPPORTED_LOCALES,
                'translations' => $translations,
            ]);
        }

        $response = $next($request);

        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        // 1. Query parameter (highest priority)
        if ($lang = $request->query('lang')) {
            $locale = $this->normalize($lang);
            if ($locale) {
                session(['locale' => $locale]);
                return $locale;
            }
        }

        // 2. User preference
        if ($request->user()) {
            $pref = \App\Models\UserPreference::where('user_id', $request->user()->id)
                ->where('key', 'locale')
                ->first();

            if ($pref) {
                $value = is_array($pref->value) ? ($pref->value['locale'] ?? null) : $pref->value;
                $locale = $this->normalize($value);
                if ($locale) {
                    return $locale;
                }
            }
        }

        // 3. Session
        if ($locale = $this->normalize(session('locale'))) {
            return $locale;
        }

        // 4. Accept-Language header
        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
        if ($preferred && in_array($preferred, self::SUPPORTED_LOCALES)) {
            return $preferred;
        }

        // 5. Default
        return config('app.locale', 'en');
    }

    private function normalize(?string $locale): ?string
    {
        if (! $locale) {
            return null;
        }

        // Exact match
        if (in_array($locale, self::SUPPORTED_LOCALES)) {
            return $locale;
        }

        // Try base language (e.g., zh -> zh-Hans)
        foreach (self::SUPPORTED_LOCALES as $supported) {
            if (str_starts_with($supported, $locale)) {
                return $supported;
            }
        }

        return null;
    }

    private function loadTranslations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! file_exists($path)) {
            // Fallback to English
            $path = lang_path('en.json');
        }

        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        return json_decode($content, true) ?? [];
    }
}
