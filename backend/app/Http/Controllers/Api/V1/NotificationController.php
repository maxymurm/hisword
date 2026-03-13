<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Register a push subscription (device token).
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'token' => ['required', 'string', 'max:4096'],
            'device_id' => ['nullable', 'string', 'uuid'],
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['boolean'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'daily_reminder_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'quiet_hours_start' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'quiet_hours_end' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $subscription = PushSubscription::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'],
                'token' => $validated['token'],
            ],
            [
                'device_id' => $validated['device_id'] ?? null,
                'preferences' => $validated['preferences'] ?? PushSubscription::defaultPreferences(),
                'timezone' => $validated['timezone'] ?? 'UTC',
                'daily_reminder_time' => $validated['daily_reminder_time'] ?? null,
                'quiet_hours_start' => $validated['quiet_hours_start'] ?? null,
                'quiet_hours_end' => $validated['quiet_hours_end'] ?? null,
                'is_active' => true,
            ]
        );

        return response()->json([
            'data' => $subscription,
            'message' => 'Push subscription registered',
        ], 201);
    }

    /**
     * Unsubscribe (deactivate) a push subscription.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $deleted = PushSubscription::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->update(['is_active' => false]);

        return response()->json([
            'success' => $deleted > 0,
            'message' => $deleted > 0 ? 'Unsubscribed' : 'Subscription not found',
        ]);
    }

    /**
     * Update notification preferences for a subscription.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'preferences' => ['required', 'array'],
            'preferences.verse_of_day' => ['nullable', 'boolean'],
            'preferences.reading_plan' => ['nullable', 'boolean'],
            'preferences.new_module' => ['nullable', 'boolean'],
            'preferences.sync' => ['nullable', 'boolean'],
            'quiet_hours_start' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'quiet_hours_end' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'daily_reminder_time' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ]);

        $subscription = PushSubscription::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->first();

        if (! $subscription) {
            return response()->json(['error' => 'Subscription not found'], 404);
        }

        $subscription->update([
            'preferences' => $validated['preferences'],
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? $subscription->quiet_hours_start,
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? $subscription->quiet_hours_end,
            'daily_reminder_time' => $validated['daily_reminder_time'] ?? $subscription->daily_reminder_time,
        ]);

        return response()->json([
            'data' => $subscription->fresh(),
            'message' => 'Preferences updated',
        ]);
    }

    /**
     * List notification history (in-app notification center).
     */
    public function history(Request $request): JsonResponse
    {
        $notifications = NotificationLog::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($notifications);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = NotificationLog::where('user_id', $request->user()->id)
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a notification as read.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = NotificationLog::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'data' => $notification,
            'message' => 'Marked as read',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = NotificationLog::where('user_id', $request->user()->id)
            ->unread()
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return response()->json([
            'updated' => $updated,
            'message' => 'All notifications marked as read',
        ]);
    }
}
