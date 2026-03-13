<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_notifications(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);
    }

    public function test_can_subscribe_to_push(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/notifications/subscribe', [
                'platform' => 'web',
                'token' => 'test-push-token-abc123',
            ]);

        $response->assertSuccessful();
    }

    public function test_notifications_require_auth(): void
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertStatus(401);
    }
}
