<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send a notification to a specific user across all their active subscriptions.
     */
    public function sendToUser(User $user, string $type, string $title, string $body, array $data = []): void
    {
        $subscriptions = $user->pushSubscriptions()
            ->active()
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->isTypeEnabled($type)) {
                continue;
            }

            if ($subscription->isInQuietHours()) {
                continue;
            }

            $this->sendToSubscription($subscription, $title, $body, $data);
        }

        // Log the notification
        NotificationLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'status' => 'sent',
        ]);
    }

    /**
     * Send a notification to a specific push subscription.
     */
    public function sendToSubscription(PushSubscription $subscription, string $title, string $body, array $data = []): bool
    {
        try {
            match ($subscription->platform) {
                'android' => $this->sendFcm($subscription->token, $title, $body, $data),
                'ios' => $this->sendApns($subscription->token, $title, $body, $data),
                'web' => $this->sendWebPush($subscription->token, $title, $body, $data),
                default => Log::warning("Unknown push platform: {$subscription->platform}"),
            };

            $subscription->update(['last_notified_at' => now()]);

            return true;
        } catch (\Exception $e) {
            Log::error("Push notification failed: {$e->getMessage()}", [
                'subscription_id' => $subscription->id,
                'platform' => $subscription->platform,
            ]);

            return false;
        }
    }

    /**
     * Send via Firebase Cloud Messaging (Android).
     */
    private function sendFcm(string $token, string $title, string $body, array $data): void
    {
        $serverKey = config('services.fcm.server_key');

        if (! $serverKey) {
            Log::warning('FCM server key not configured');

            return;
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'HisWord_default',
                        'icon' => 'ic_notification',
                        'color' => '#6366f1',
                    ],
                ],
            ],
        ];

        // HTTP v1 API call would go here
        Log::info('FCM notification queued', ['token' => substr($token, 0, 20) . '...']);
    }

    /**
     * Send via Apple Push Notification Service (iOS).
     */
    private function sendApns(string $token, string $title, string $body, array $data): void
    {
        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'badge' => 1,
                'sound' => 'default',
                'mutable-content' => 1,
            ],
            'data' => $data,
        ];

        // APNs HTTP/2 call would go here
        Log::info('APNs notification queued', ['token' => substr($token, 0, 20) . '...']);
    }

    /**
     * Send via Web Push API.
     */
    private function sendWebPush(string $subscriptionJson, string $title, string $body, array $data): void
    {
        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/icons/icon-192x192.png',
            'badge' => '/icons/badge-72x72.png',
            'data' => $data,
            'requireInteraction' => false,
        ];

        // Web Push API call would go here
        Log::info('Web push notification queued');
    }

    /**
     * Send a silent sync notification (data-only, no visible notification).
     */
    public function sendSilentSync(User $user, array $syncData = []): void
    {
        $subscriptions = $user->pushSubscriptions()
            ->active()
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->isTypeEnabled('sync')) {
                continue;
            }

            try {
                match ($subscription->platform) {
                    'android' => $this->sendFcmData($subscription->token, $syncData),
                    'ios' => $this->sendApnsSilent($subscription->token, $syncData),
                    default => null,
                };
            } catch (\Exception $e) {
                Log::error("Silent sync push failed: {$e->getMessage()}");
            }
        }
    }

    private function sendFcmData(string $token, array $data): void
    {
        Log::info('FCM data-only notification queued', ['token' => substr($token, 0, 20) . '...']);
    }

    private function sendApnsSilent(string $token, array $data): void
    {
        Log::info('APNs silent notification queued', ['token' => substr($token, 0, 20) . '...']);
    }
}
