/**
 * Browser Push Notification subscription management.
 * Handles permission requests, PushSubscription creation, and server registration.
 */

/** Request notification permission from the user. */
export async function requestPermission(): Promise<NotificationPermission> {
    if (!('Notification' in window)) return 'denied';
    if (Notification.permission !== 'default') return Notification.permission;
    return Notification.requestPermission();
}

/** Subscribe to push notifications via the service worker. */
export async function subscribeToPush(): Promise<boolean> {
    try {
        const permission = await requestPermission();
        if (permission !== 'granted') return false;

        const reg = await navigator.serviceWorker.ready;

        // Get VAPID public key from server
        const meta = document.querySelector('meta[name="vapid-public-key"]');
        const vapidKey = meta?.getAttribute('content');
        if (!vapidKey) {
            console.warn('[Push] No VAPID public key found');
            return false;
        }

        const subscription = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey),
        });

        // Send subscription to server
        const resp = await fetch('/notifications/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                platform: 'web',
                token: JSON.stringify(subscription),
                device_id: getDeviceId(),
            }),
        });

        return resp.ok;
    } catch (err) {
        console.error('[Push] Subscribe failed:', err);
        return false;
    }
}

/** Unsubscribe from push notifications. */
export async function unsubscribeFromPush(): Promise<boolean> {
    try {
        const reg = await navigator.serviceWorker.ready;
        const subscription = await reg.pushManager.getSubscription();

        if (subscription) {
            await subscription.unsubscribe();
        }

        const resp = await fetch('/notifications/unsubscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ device_id: getDeviceId() }),
        });

        return resp.ok;
    } catch (err) {
        console.error('[Push] Unsubscribe failed:', err);
        return false;
    }
}

/** Check if the user is currently subscribed to push. */
export async function isSubscribed(): Promise<boolean> {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
    try {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        return sub !== null;
    } catch {
        return false;
    }
}

/** Check if push notifications are supported in this browser. */
export function isPushSupported(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

// ── Helpers ─────────────────────────────────────────────────────

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    const arr = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) {
        arr[i] = raw.charCodeAt(i);
    }
    return arr;
}

function getDeviceId(): string {
    let id = localStorage.getItem('ps-device-id');
    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem('ps-device-id', id);
    }
    return id;
}

function getCsrfToken(): string {
    return decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''
    );
}
