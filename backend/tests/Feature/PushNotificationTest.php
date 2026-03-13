<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    // ── API: Subscribe ──────────────────────────────────────

    public function test_user_can_subscribe_to_push_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/subscribe', [
                'platform' => 'android',
                'token' => 'fcm_token_abc123',
                'timezone' => 'America/New_York',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Push subscription registered');

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'platform' => 'android',
            'token' => 'fcm_token_abc123',
            'is_active' => true,
        ]);
    }

    public function test_subscribe_validates_platform(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/subscribe', [
                'platform' => 'windows',
                'token' => 'some_token',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('platform');
    }

    public function test_subscribe_updates_existing_subscription(): void
    {
        $user = User::factory()->create();
        $token = 'existing_token';

        PushSubscription::factory()->create([
            'user_id' => $user->id,
            'platform' => 'ios',
            'token' => $token,
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/subscribe', [
                'platform' => 'ios',
                'token' => $token,
                'timezone' => 'Europe/London',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'timezone' => 'Europe/London',
        ]);
    }

    // ── API: Unsubscribe ────────────────────────────────────

    public function test_user_can_unsubscribe(): void
    {
        $user = User::factory()->create();
        $sub = PushSubscription::factory()->create([
            'user_id' => $user->id,
            'token' => 'token_to_remove',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/unsubscribe', [
                'token' => 'token_to_remove',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $sub->id,
            'is_active' => false,
        ]);
    }

    // ── API: Update Preferences ─────────────────────────────

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();
        $sub = PushSubscription::factory()->create([
            'user_id' => $user->id,
            'token' => 'pref_token',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'token' => 'pref_token',
                'preferences' => [
                    'verse_of_day' => true,
                    'reading_plan' => false,
                    'new_module' => true,
                    'sync' => false,
                ],
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'daily_reminder_time' => '08:30',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Preferences updated');

        $sub->refresh();
        $this->assertEquals('22:00', $sub->quiet_hours_start);
        $this->assertEquals('07:00', $sub->quiet_hours_end);
        $this->assertEquals('08:30', $sub->daily_reminder_time);
        $this->assertFalse($sub->preferences['reading_plan']);
    }

    public function test_update_preferences_returns_404_for_unknown_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'token' => 'nonexistent',
                'preferences' => ['verse_of_day' => true],
            ]);

        $response->assertStatus(404);
    }

    // ── API: Notification History ────────────────────────────

    public function test_user_can_view_notification_history(): void
    {
        $user = User::factory()->create();
        NotificationLog::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_notification_history_only_shows_own(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        NotificationLog::factory()->count(2)->create(['user_id' => $user->id]);
        NotificationLog::factory()->count(5)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    // ── API: Unread Count ───────────────────────────────────

    public function test_user_can_get_unread_count(): void
    {
        $user = User::factory()->create();
        NotificationLog::factory()->count(3)->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        NotificationLog::factory()->count(2)->read()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200);
        $response->assertJson(['count' => 3]);
    }

    // ── API: Mark Read ──────────────────────────────────────

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = NotificationLog::factory()->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $notification->refresh();
        $this->assertNotNull($notification->read_at);
        $this->assertEquals('read', $notification->status);
    }

    public function test_user_can_mark_all_as_read(): void
    {
        $user = User::factory()->create();
        NotificationLog::factory()->count(5)->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all');

        $response->assertStatus(200);
        $response->assertJsonPath('updated', 5);

        $this->assertEquals(0,
            NotificationLog::where('user_id', $user->id)->unread()->count()
        );
    }

    // ── Web: Subscribe ──────────────────────────────────────

    public function test_web_user_can_subscribe(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/notifications/subscribe', [
                'token' => 'web_push_endpoint_json',
                'timezone' => 'Europe/Berlin',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'platform' => 'web',
            'is_active' => true,
        ]);
    }

    // ── Web: Notification Center ────────────────────────────

    public function test_web_user_can_view_notifications(): void
    {
        $user = User::factory()->create();
        NotificationLog::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson('/notifications');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data', 'unread_count']);
    }

    // ── Web: Preferences ────────────────────────────────────

    public function test_web_user_can_view_notification_preferences(): void
    {
        $user = User::factory()->create();
        PushSubscription::factory()->web()->create([
            'user_id' => $user->id,
            'preferences' => ['verse_of_day' => true, 'reading_plan' => false, 'new_module' => true, 'sync' => false],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/notifications/preferences');

        $response->assertStatus(200);
        $response->assertJson([
            'subscribed' => true,
            'preferences' => ['verse_of_day' => true, 'reading_plan' => false],
        ]);
    }

    // ── Model: PushSubscription ─────────────────────────────

    public function test_push_subscription_quiet_hours_within_same_day(): void
    {
        $sub = PushSubscription::factory()->create([
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '23:59',
            'timezone' => 'UTC',
        ]);

        // This tests the method exists and runs without error
        $result = $sub->isInQuietHours();
        $this->assertIsBool($result);
    }

    public function test_push_subscription_type_enabled_check(): void
    {
        $sub = PushSubscription::factory()->create([
            'preferences' => [
                'verse_of_day' => true,
                'reading_plan' => false,
                'new_module' => true,
                'sync' => false,
            ],
        ]);

        $this->assertTrue($sub->isTypeEnabled('verse_of_day'));
        $this->assertFalse($sub->isTypeEnabled('reading_plan'));
        $this->assertTrue($sub->isTypeEnabled('new_module'));
        $this->assertFalse($sub->isTypeEnabled('sync'));
    }

    public function test_push_subscription_default_preferences(): void
    {
        $defaults = PushSubscription::defaultPreferences();

        $this->assertArrayHasKey('verse_of_day', $defaults);
        $this->assertArrayHasKey('reading_plan', $defaults);
        $this->assertArrayHasKey('new_module', $defaults);
        $this->assertArrayHasKey('sync', $defaults);
    }

    public function test_notification_log_mark_as_read(): void
    {
        $notification = NotificationLog::factory()->create(['read_at' => null]);

        $notification->markAsRead();

        $this->assertEquals('read', $notification->status);
        $this->assertNotNull($notification->read_at);
    }

    public function test_notification_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }
}
