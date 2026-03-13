<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && !$request->ajax()) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            if ($request->is('api/*')) {
                $response->headers->set('Cache-Control', 'private, max-age=60');
            }
        }

        return $response;
    }
}
