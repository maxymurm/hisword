<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ApiRoutesTest extends TestCase
{
    /**
     * Public auth routes should return a response (not 404).
     */
    public function test_public_auth_routes_are_reachable(): void
    {
        $routes = [
            ['POST', '/api/v1/auth/register'],
            ['POST', '/api/v1/auth/login'],
            ['POST', '/api/v1/auth/forgot-password'],
            ['POST', '/api/v1/auth/reset-password'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            // Should NOT be 404 (route exists); may be 422 (validation) or 501 (stub)
            $this->assertNotEquals(404, $response->getStatusCode(), "Route {$method} {$uri} returned 404");
        }
    }

    /**
     * Protected routes should return 401 when unauthenticated.
     */
    public function test_protected_routes_require_authentication(): void
    {
        $routes = [
            ['GET', '/api/v1/auth/user'],
            ['GET', '/api/v1/modules'],
            ['GET', '/api/v1/bookmarks'],
            ['GET', '/api/v1/bookmark-folders'],
            ['GET', '/api/v1/highlights'],
            ['GET', '/api/v1/notes'],
            ['GET', '/api/v1/pins'],
            ['GET', '/api/v1/history'],
            ['GET', '/api/v1/search'],
            ['GET', '/api/v1/settings'],
            ['GET', '/api/v1/verse-of-the-day'],
            ['GET', '/api/v1/reading-plans'],
            ['GET', '/api/v1/devices'],
        ];

        foreach ($routes as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $this->assertEquals(401, $response->getStatusCode(), "Route {$method} {$uri} did not return 401 for unauthenticated request");
        }
    }

    /**
     * API response format should be JSON.
     */
    public function test_api_returns_json(): void
    {
        $response = $this->json('POST', '/api/v1/auth/login');
        $response->assertHeader('content-type', 'application/json');
    }
}
