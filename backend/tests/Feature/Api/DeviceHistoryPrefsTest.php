<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\History;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceHistoryPrefsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── Devices ──────────────────────────────

    public function test_can_register_device(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/devices', [
                'device_id' => 'abc-123',
                'name' => 'My iPhone',
                'platform' => 'ios',
                'app_version' => '1.0.0',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.device_id', 'abc-123');
    }

    public function test_can_list_devices(): void
    {
        Device::create([
            'user_id' => $this->user->id,
            'device_id' => 'd1',
            'name' => 'Device 1',
            'platform' => 'android',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/devices')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_register_same_device_updates(): void
    {
        Device::create([
            'user_id' => $this->user->id,
            'device_id' => 'd1',
            'name' => 'Old Name',
            'platform' => 'android',
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/devices', [
                'device_id' => 'd1',
                'name' => 'New Name',
                'platform' => 'android',
                'app_version' => '2.0',
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('devices', ['name' => 'New Name']);
    }

    public function test_can_delete_device(): void
    {
        $device = Device::create([
            'user_id' => $this->user->id,
            'device_id' => 'd1',
            'name' => 'To Delete',
            'platform' => 'ios',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/devices/{$device->id}")
            ->assertOk();

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    // ── History ──────────────────────────────

    public function test_can_store_history(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/history', [
                'book_osis_id' => 'Gen',
                'chapter_number' => 1,
                'verse_number' => 1,
                'module_key' => 'KJV',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.module_key', 'KJV');
    }

    public function test_can_list_history(): void
    {
        History::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'module_key' => 'KJV',
            'created_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/history')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_clear_all_history(): void
    {
        History::create([
            'user_id' => $this->user->id,
            'book_osis_id' => 'Gen',
            'chapter_number' => 1,
            'verse_number' => 1,
            'module_key' => 'KJV',
            'created_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->deleteJson('/api/v1/history')
            ->assertOk();

        $this->assertDatabaseHas('history', [
            'user_id' => $this->user->id,
            'is_deleted' => true,
        ]);
    }

    // ── User Preferences ────────────────────

    public function test_can_update_preferences(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/settings', [
                'settings' => [
                    'theme' => 'dark',
                    'font_size' => 16,
                    'default_module' => 'KJV',
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'key' => 'theme',
        ]);
    }

    public function test_can_get_preferences(): void
    {
        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'DARK'],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings');

        $response->assertOk()
            ->assertJsonPath('data.settings.ui_theme_variant', 'DARK');
    }
}
