/**
 * HisWord IndexedDB Sync Queue
 *
 * Stores pending annotation changes when offline, replays on reconnect.
 * Uses the Background Sync API via the service worker.
 */

const DB_NAME = 'HisWord-sync';
const DB_VERSION = 1;
const QUEUE_STORE = 'sync-queue';
const META_STORE = 'sync-meta';

function openDB(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(QUEUE_STORE)) {
                db.createObjectStore(QUEUE_STORE, { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains(META_STORE)) {
                db.createObjectStore(META_STORE, { keyPath: 'key' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export interface SyncChange {
    entity_type: string;
    entity_id: string;
    operation: 'create' | 'update' | 'delete';
    data: Record<string, unknown>;
    timestamp: string;
}

interface QueuedChange extends SyncChange {
    id?: number;
}

/** Enqueue a change for background sync. */
export async function enqueueChange(change: SyncChange): Promise<void> {
    const db = await openDB();
    const tx = db.transaction(QUEUE_STORE, 'readwrite');
    tx.objectStore(QUEUE_STORE).add(change);
    await txComplete(tx);
    db.close();

    // Request background sync if available
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
        const reg = await navigator.serviceWorker.ready;
        try {
            await (reg as unknown as { sync: { register: (tag: string) => Promise<void> } }).sync.register('sync-annotations');
        } catch {
            // Fallback: try immediate push
            await flushQueue();
        }
    } else {
        // No Background Sync API — push immediately if online
        if (navigator.onLine) {
            await flushQueue();
        }
    }
}

/** Get all pending changes. */
export async function getPendingChanges(): Promise<QueuedChange[]> {
    const db = await openDB();
    const tx = db.transaction(QUEUE_STORE, 'readonly');
    const store = tx.objectStore(QUEUE_STORE);
    const items: QueuedChange[] = await getAllFromStore(store);
    db.close();
    return items;
}

/** Get count of pending changes. */
export async function getPendingCount(): Promise<number> {
    const db = await openDB();
    const tx = db.transaction(QUEUE_STORE, 'readonly');
    const store = tx.objectStore(QUEUE_STORE);
    return new Promise((resolve, reject) => {
        const req = store.count();
        req.onsuccess = () => { resolve(req.result); db.close(); };
        req.onerror = () => { reject(req.error); db.close(); };
    });
}

/** Flush the queue by posting all pending changes to the sync API. */
export async function flushQueue(): Promise<{ success: boolean; synced: number }> {
    const changes = await getPendingChanges();
    if (changes.length === 0) return { success: true, synced: 0 };

    const deviceId = getDeviceId();

    try {
        const resp = await fetch('/api/v1/sync/push', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                device_id: deviceId,
                changes: changes.map(({ entity_type, entity_id, operation, data, timestamp }) => ({
                    entity_type,
                    entity_id,
                    operation,
                    data,
                    vector_clock: {},
                    timestamp,
                })),
            }),
        });

        if (!resp.ok) {
            return { success: false, synced: 0 };
        }

        // Clear synced items
        const db = await openDB();
        const tx = db.transaction(QUEUE_STORE, 'readwrite');
        const store = tx.objectStore(QUEUE_STORE);
        for (const change of changes) {
            if (change.id !== undefined) {
                store.delete(change.id);
            }
        }
        await txComplete(tx);
        db.close();

        return { success: true, synced: changes.length };
    } catch {
        return { success: false, synced: 0 };
    }
}

/** Get or create a persistent device ID. */
function getDeviceId(): string {
    let id = localStorage.getItem('ps-device-id');
    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem('ps-device-id', id);
    }
    return id;
}

/** Save last sync timestamp. */
export async function setLastSync(ts: string): Promise<void> {
    const db = await openDB();
    const tx = db.transaction(META_STORE, 'readwrite');
    tx.objectStore(META_STORE).put({ key: 'last-sync', value: ts });
    await txComplete(tx);
    db.close();
}

/** Get last sync timestamp. */
export async function getLastSync(): Promise<string | null> {
    const db = await openDB();
    const tx = db.transaction(META_STORE, 'readonly');
    const store = tx.objectStore(META_STORE);
    return new Promise((resolve, reject) => {
        const req = store.get('last-sync');
        req.onsuccess = () => { resolve(req.result?.value ?? null); db.close(); };
        req.onerror = () => { reject(req.error); db.close(); };
    });
}

// ── IDB Helpers ─────────────────────────────────────────────────

function txComplete(tx: IDBTransaction): Promise<void> {
    return new Promise((resolve, reject) => {
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

function getAllFromStore<T>(store: IDBObjectStore): Promise<T[]> {
    return new Promise((resolve, reject) => {
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result as T[]);
        req.onerror = () => reject(req.error);
    });
}
