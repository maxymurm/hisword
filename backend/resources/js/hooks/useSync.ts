import { useState, useEffect, useCallback } from 'react';
import { usePage } from '@inertiajs/react';
import { getPendingCount, flushQueue, getLastSync, setLastSync } from '@/services/syncQueue';

export type SyncStatus = 'synced' | 'pending' | 'syncing' | 'offline' | 'error';

interface UseSyncReturn {
    status: SyncStatus;
    pendingCount: number;
    lastSync: string | null;
    syncNow: () => Promise<void>;
}

export function useSync(): UseSyncReturn {
    const [status, setStatus] = useState<SyncStatus>(navigator.onLine ? 'synced' : 'offline');
    const [pendingCount, setPendingCount] = useState(0);
    const [lastSync, setLastSyncState] = useState<string | null>(null);
    const { auth } = usePage<{ auth: { user?: { id: number } } }>().props;

    const refresh = useCallback(async () => {
        try {
            const count = await getPendingCount();
            setPendingCount(count);
            const ls = await getLastSync();
            setLastSyncState(ls);

            if (!navigator.onLine) {
                setStatus('offline');
            } else if (count > 0) {
                setStatus('pending');
            } else {
                setStatus('synced');
            }
        } catch {
            setStatus('error');
        }
    }, []);

    const syncNow = useCallback(async () => {
        if (!navigator.onLine) return;
        setStatus('syncing');
        const result = await flushQueue();
        if (result.success) {
            const ts = new Date().toISOString();
            await setLastSync(ts);
            setLastSyncState(ts);
            setPendingCount(0);
            setStatus('synced');
        } else {
            setStatus('error');
            // Reset to pending after a moment
            setTimeout(() => refresh(), 3000);
        }
    }, [refresh]);

    useEffect(() => {
        refresh();

        const onOnline = () => { refresh(); syncNow(); };
        const onOffline = () => setStatus('offline');

        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);

        // Listen for SW sync-complete messages
        const onMessage = (event: MessageEvent) => {
            if (event.data?.type === 'sync-complete') {
                refresh();
            }
        };
        navigator.serviceWorker?.addEventListener('message', onMessage);

        // Real-time sync via Laravel Reverb (Pusher protocol)
        const userId = auth?.user?.id;
        let echoChannel: ReturnType<typeof window.Echo.private> | null = null;
        if (userId && typeof window.Echo !== 'undefined') {
            const deviceId = localStorage.getItem('ps-device-id') ?? '';
            echoChannel = window.Echo.private(`sync.${userId}`);
            echoChannel.listen('.marker.changed', (e: { device_id?: string }) => {
                // Ignore own device's events
                if (e.device_id === deviceId) return;
                refresh();
            });
            echoChannel.listen('.plan.progress.changed', (e: { device_id?: string }) => {
                if (e.device_id === deviceId) return;
                refresh();
            });
        }

        // Poll every 30s
        const interval = setInterval(refresh, 30000);

        return () => {
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
            navigator.serviceWorker?.removeEventListener('message', onMessage);
            clearInterval(interval);
            if (echoChannel) {
                echoChannel.stopListening('.marker.changed');
                echoChannel.stopListening('.plan.progress.changed');
                window.Echo.leave(`sync.${userId}`);
            }
        };
    }, [refresh, syncNow, auth?.user?.id]);

    return { status, pendingCount, lastSync, syncNow };
}
