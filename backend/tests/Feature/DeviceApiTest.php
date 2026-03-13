<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Registration ────────────────────────────────────

    public function test_register_device(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/devices', [
            'device_id' => 'test-device-001',
            'platform' => 'android',
            'name' => 'Pixel 8',
            'app_version' => '2.0.0',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.device_id', 'test-device-001')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.name', 'Pixel 8');

        $this->assertDatabaseHas('devices', [
            'user_id' => $this->user->id,
            'device_id' => 'test-device-001',
        ]);
    }

    public function test_register_device_upserts_on_same_device_id(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/devices', [
            'device_id' => 'test-device-001',
            'platform' => 'android',
            'name' => 'Old Name',
        ]);

        $this->actingAs($this->user)->postJson('/api/v1/devices', [
            'device_id' => 'test-device-001',
            'platform' => 'android',
            'name' => 'New Name',
            'app_version' => '2.1.0',
        ]);

        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('devices', [
            'device_id' => 'test-device-001',
            'name' => 'New Name',
            'app_version' => '2.1.0',
        ]);
    }

    public function test_register_device_validates_platform(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/devices', [
            'device_id' => 'test-device-001',
            'platform' => 'windows',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('platform');
    }

    public function test_register_device_requires_device_id(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/devices', [
            'platform' => 'android',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('device_id');
    }

    public function test_max_device_limit(): void
    {
        // Register max devices (10)
        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($this->user)->postJson('/api/v1/devices', [
                'device_id' => "device-$i",
                'platform' => 'android',
            ]);
        }

        $this->assertDatabaseCount('devices', 10);

        // 11th should fail
        $response = $this->actingAs($this->user)->postJson('/api/v1/devices', [
            'device_id' => 'device-11',
            'platform' => 'android',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('devices', 10);
    }

    public function test_re_registering_existing_device_does_not_count_against_limit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($this->user)->postJson('/api/v1/devices', [
                'device_id' => "device-$i",
                'platform' => 'android',
            ]);
        }

        // Re-registering device-1 should succeed (upsert)
        $response = $this->actingAs($this->user)->postJson('/api/v1/devices', [
            'device_id' => 'device-1',
            'platform' => 'android',
            'name' => 'Updated Phone',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('devices', 10);
    }

    // ── Listing ─────────────────────────────────────────

    public function test_list_devices(): void
    {
        Device::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/devices');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_devices_excludes_other_users(): void
    {
        Device::factory()->count(2)->create(['user_id' => $this->user->id]);
        Device::factory()->count(3)->create(); // other user's devices

        $response = $this->actingAs($this->user)->getJson('/api/v1/devices');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_devices_ordered_by_last_sync(): void
    {
        Device::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Oldest',
            'last_sync_at' => now()->subDays(3),
        ]);
        Device::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Newest',
            'last_sync_at' => now(),
        ]);
        Device::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Middle',
            'last_sync_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/devices');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Newest', 'Middle', 'Oldest'], $names);
    }

    // ── Show ────────────────────────────────────────────

    public function test_show_device(): void
    {
        $device = Device::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/devices/{$device->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $device->id);
    }

    public function test_show_device_not_found(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/devices/nonexistent-id');

        $response->assertNotFound();
    }

    public function test_cannot_view_other_users_device(): void
    {
        $otherDevice = Device::factory()->create(); // belongs to another user

        $response = $this->actingAs($this->user)->getJson("/api/v1/devices/{$otherDevice->id}");

        $response->assertNotFound();
    }

    // ── Update ──────────────────────────────────────────

    public function test_update_device_name(): void
    {
        $device = Device::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/devices/{$device->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_push_token(): void
    {
        $device = Device::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/devices/{$device->id}", [
            'push_token' => 'new-fcm-token-12345',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.push_token', 'new-fcm-token-12345');
    }

    public function test_cannot_update_other_users_device(): void
    {
        $otherDevice = Device::factory()->create();

        $response = $this->actingAs($this->user)->putJson("/api/v1/devices/{$otherDevice->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertNotFound();
    }

    // ── Delete ──────────────────────────────────────────

    public function test_delete_device(): void
    {
        $device = Device::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/devices/{$device->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    public function test_cannot_delete_other_users_device(): void
    {
        $otherDevice = Device::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/devices/{$otherDevice->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('devices', ['id' => $otherDevice->id]);
    }

    // ── Auth ────────────────────────────────────────────

    public function test_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/devices')->assertUnauthorized();
        $this->postJson('/api/v1/devices')->assertUnauthorized();
    }
}
