<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Register a web push subscription.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'preferences' => ['nullable', 'array'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ]);

        $subscription = PushSubscription::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'platform' => 'web',
                'token' => $validated['token'],
            ],
            [
                'preferences' => $validated['preferences'] ?? PushSubscription::defaultPreferences(),
                'timezone' => $validated['timezone'] ?? 'UTC',
                'is_active' => true,
            ]
        );

        return response()->json([
            'data' => $subscription,
            'message' => 'Web push subscription registered',
        ], 201);
    }

    /**
     * Unsubscribe from web push.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('platform', 'web')
            ->where('token', $validated['token'])
            ->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }

    /**
     * Get notification history for in-app notification center.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = NotificationLog::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $notifications,
            'unread_count' => NotificationLog::where('user_id', $request->user()->id)
                ->unread()
                ->count(),
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = NotificationLog::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        NotificationLog::where('user_id', $request->user()->id)
            ->unread()
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * Get notification preferences for current user.
     */
    public function preferences(Request $request): JsonResponse
    {
        $subscription = PushSubscription::where('user_id', $request->user()->id)
            ->where('platform', 'web')
            ->active()
            ->first();

        return response()->json([
            'subscribed' => $subscription !== null,
            'preferences' => $subscription?->preferences ?? PushSubscription::defaultPreferences(),
            'quiet_hours_start' => $subscription?->quiet_hours_start,
            'quiet_hours_end' => $subscription?->quiet_hours_end,
            'daily_reminder_time' => $subscription?->daily_reminder_time,
        ]);
    }
}
