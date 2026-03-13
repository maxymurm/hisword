<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents N+1 query issues in non-production environments
 * and logs slow queries for analysis.
 */
class QueryMonitor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            \Illuminate\Database\Eloquent\Model::preventLazyLoading();
        }

        $startTime = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000;

        // Log slow requests (> 500ms) in production
        if ($duration > 500 && app()->environment('production')) {
            \Illuminate\Support\Facades\Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'duration_ms' => round($duration, 2),
                'user_id' => $request->user()?->id,
            ]);
        }

        // Add Server-Timing header (useful for debugging)
        if (! app()->environment('production')) {
            $response->headers->set('Server-Timing', sprintf('total;dur=%.2f', $duration));
        }

        return $response;
    }
}
