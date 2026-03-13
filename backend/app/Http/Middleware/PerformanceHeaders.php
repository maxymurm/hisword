<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds performance-related headers and handles caching directives.
 */
class PerformanceHeaders
{
    /**
     * Static / immutable asset patterns.
     */
    private const CACHEABLE_PATTERNS = [
        '#^/build/#',      // Vite build assets
        '#^/images/#',     // Static images
        '#^/fonts/#',      // Web fonts
        '#^/favicon#',     // Favicons
        '#^/manifest#',    // PWA manifest
        '#^/sw\.js#',      // Service worker
    ];

    /**
     * Patterns that should never be cached.
     */
    private const NO_CACHE_PATTERNS = [
        '#^/api/#',             // API responses are dynamic
        '#^/broadcasting/#',    // WebSocket auth
        '#^/sanctum/#',         // CSRF cookie
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $path = $request->getPathInfo();

        // Static assets: aggressive caching
        foreach (self::CACHEABLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
                $response->headers->set('Vary', 'Accept-Encoding');

                return $response;
            }
        }

        // No-cache endpoints
        foreach (self::NO_CACHE_PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

                return $response;
            }
        }

        // Well-known files (deep links): short cache
        if (str_starts_with($path, '/.well-known/')) {
            $response->headers->set('Cache-Control', 'public, max-age=3600');

            return $response;
        }

        // Default: private, short cache for HTML pages
        if ($request->wantsJson()) {
            $response->headers->set('Cache-Control', 'private, no-cache');
        } else {
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
        }

        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
