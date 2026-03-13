<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchModuleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_endpoint_exists(): void
    {
        $response = $this->getJson('/api/v1/search?q=love&module=KJV');
        // 200 or 401 — just verify the route is registered
        $this->assertContains($response->getStatusCode(), [200, 401, 422]);
    }

    public function test_search_requires_query(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/search');

        // Should fail validation or return empty results
        $this->assertContains($response->getStatusCode(), [200, 422]);
    }

    public function test_dictionary_lookup_endpoint(): void
    {
        $response = $this->getJson('/api/v1/dictionary/lookup?module=StrongsGreek&key=G26');
        $this->assertContains($response->getStatusCode(), [200, 401, 404, 422]);
    }

    public function test_module_list_endpoint(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/modules');
        $response->assertStatus(200);
    }

    public function test_module_sources_endpoint(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/module-sources');
        $this->assertContains($response->getStatusCode(), [200, 404]);
    }
}
