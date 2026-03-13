<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\V1\UserPreferenceController;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPreferenceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ── GET /settings ───────────────────────────────

    public function test_index_returns_defaults_when_no_preferences_stored(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'settings',
                    'categories' => [
                        'appearance',
                        'reader',
                        'modules',
                        'notifications',
                        'sync',
                    ],
                    'local_only_keys',
                ],
            ]);

        // Check defaults are returned
        $settings = $response->json('data.settings');
        $this->assertEquals('LIGHT', $settings['ui_theme_variant']);
        $this->assertEquals(16, $settings['reader_font_size']);
        $this->assertFalse($settings['reader_show_strongs']);
        $this->assertTrue($settings['notification_verse_of_day']);
    }

    public function test_index_merges_stored_with_defaults(): void
    {
        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'DARK'],
        ]);

        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'reader_font_size',
            'value' => ['value' => 20],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings');

        $response->assertOk();
        $settings = $response->json('data.settings');

        // Stored values override defaults
        $this->assertEquals('DARK', $settings['ui_theme_variant']);
        $this->assertEquals(20, $settings['reader_font_size']);

        // Defaults still present for un-stored keys
        $this->assertFalse($settings['reader_show_strongs']);
        $this->assertTrue($settings['sync_enabled']);
    }

    public function test_index_returns_local_only_keys_list(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings');

        $localOnly = $response->json('data.local_only_keys');
        $this->assertContains('download_path', $localOnly);
        $this->assertContains('wifi_only_downloads', $localOnly);
    }

    public function test_index_returns_categories(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings');

        $categories = $response->json('data.categories');
        $this->assertArrayHasKey('appearance', $categories);
        $this->assertArrayHasKey('ui_theme_variant', $categories['appearance']);
        $this->assertArrayHasKey('reader', $categories);
        $this->assertArrayHasKey('reader_show_strongs', $categories['reader']);
    }

    // ── PUT /settings ───────────────────────────────

    public function test_update_creates_new_settings(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/settings', [
                'settings' => [
                    'ui_theme_variant' => 'DARK',
                    'reader_font_size' => 22,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.updated_count', 2)
            ->assertJsonPath('data.settings.ui_theme_variant', 'DARK');

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
        ]);
    }

    public function test_update_overwrites_existing_settings(): void
    {
        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'LIGHT'],
        ]);

        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/settings', [
                'settings' => ['ui_theme_variant' => 'DARK'],
            ]);

        $response->assertOk();

        $pref = UserPreference::where('user_id', $this->user->id)
            ->where('key', 'ui_theme_variant')
            ->first();

        $this->assertEquals(['value' => 'DARK'], $pref->value);
    }

    public function test_update_ignores_local_only_keys(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/settings', [
                'settings' => [
                    'ui_theme_variant' => 'DARK',
                    'download_path' => '/custom/path',   // local-only
                    'wifi_only_downloads' => true,        // local-only
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.updated_count', 1); // Only theme is stored

        $this->assertDatabaseMissing('user_preferences', [
            'user_id' => $this->user->id,
            'key' => 'download_path',
        ]);
    }

    public function test_update_increments_vector_clock(): void
    {
        $this->actingAs($this->user)
            ->withHeader('X-Device-Id', 'device-abc')
            ->putJson('/api/v1/settings', [
                'settings' => ['ui_theme_variant' => 'DARK'],
            ]);

        $pref = UserPreference::where('user_id', $this->user->id)
            ->where('key', 'ui_theme_variant')
            ->first();

        $this->assertArrayHasKey('device-abc', $pref->vector_clock);
        $this->assertEquals(1, $pref->vector_clock['device-abc']);

        // Update again — clock should increment
        $this->actingAs($this->user)
            ->withHeader('X-Device-Id', 'device-abc')
            ->putJson('/api/v1/settings', [
                'settings' => ['ui_theme_variant' => 'OLED'],
            ]);

        $pref->refresh();
        $this->assertEquals(2, $pref->vector_clock['device-abc']);
    }

    public function test_update_requires_settings_array(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/v1/settings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings');
    }

    // ── POST /settings/reset ────────────────────────

    public function test_reset_deletes_all_preferences(): void
    {
        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'DARK'],
        ]);
        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'reader_font_size',
            'value' => ['value' => 22],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/settings/reset');

        $response->assertOk()
            ->assertJsonPath('message', 'Settings reset to defaults');

        $this->assertDatabaseMissing('user_preferences', [
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
        ]);

        // Returned settings should be the defaults
        $settings = $response->json('data.settings');
        $this->assertEquals('LIGHT', $settings['ui_theme_variant']);
    }

    public function test_reset_does_not_affect_other_users(): void
    {
        $other = User::factory()->create();

        UserPreference::create([
            'user_id' => $other->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'DARK'],
        ]);

        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'OLED'],
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/settings/reset');

        // Other user's prefs still intact
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $other->id,
            'key' => 'ui_theme_variant',
        ]);
    }

    // ── GET /settings/diff ──────────────────────────

    public function test_diff_shows_modified_settings(): void
    {
        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'DARK'],
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings/diff');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('ui_theme_variant', $data);
        $this->assertEquals('DARK', $data['ui_theme_variant']['current']);
        $this->assertEquals('LIGHT', $data['ui_theme_variant']['default']);
    }

    public function test_diff_excludes_unchanged_settings(): void
    {
        // Store a value that matches the default
        UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'LIGHT'], // same as default
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings/diff');

        $response->assertOk();
        $this->assertArrayNotHasKey('ui_theme_variant', $response->json('data'));
    }

    public function test_diff_empty_when_no_preferences(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/settings/diff');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ── Sync Integration ────────────────────────────

    public function test_preferences_work_with_sync_push(): void
    {
        // Register a device first
        $this->actingAs($this->user)
            ->postJson('/api/v1/devices', [
                'device_id' => 'sync-device',
                'name' => 'Test Sync',
                'platform' => 'android',
            ]);

        // Create a preference first
        $pref = UserPreference::create([
            'user_id' => $this->user->id,
            'key' => 'ui_theme_variant',
            'value' => ['value' => 'DARK'],
            'vector_clock' => ['sync-device' => 1],
        ]);

        // Push an update via sync
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/sync/push', [
                'device_id' => 'sync-device',
                'changes' => [
                    [
                        'entity_type' => 'user_preference',
                        'entity_id' => $pref->id,
                        'operation' => 'update',
                        'data' => [
                            'key' => 'ui_theme_variant',
                            'value' => ['value' => 'OLED'],
                        ],
                        'vector_clock' => ['sync-device' => 2],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.applied', 1);

        $pref->refresh();
        $this->assertEquals(['value' => 'OLED'], $pref->value);
    }

    // ── Auth ────────────────────────────────────────

    public function test_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/settings')->assertStatus(401);
        $this->putJson('/api/v1/settings', ['settings' => []])->assertStatus(401);
        $this->postJson('/api/v1/settings/reset')->assertStatus(401);
        $this->getJson('/api/v1/settings/diff')->assertStatus(401);
    }
}
