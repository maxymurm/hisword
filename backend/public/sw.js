// HisWord Service Worker
// Cache strategies: cache-first for static assets, stale-while-revalidate for pages, network-first for API

const CACHE_VERSION = 'ps-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const PAGES_CACHE = `${CACHE_VERSION}-pages`;
const API_CACHE = `${CACHE_VERSION}-api`;
const CHAPTERS_CACHE = `${CACHE_VERSION}-chapters`;

const STATIC_ASSETS = [
    '/',
    '/offline',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

// ── Install ─────────────────────────────────────────────────────

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// ── Activate ────────────────────────────────────────────────────

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => key.startsWith('ps-') && !key.startsWith(CACHE_VERSION))
                    .map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// ── Fetch ───────────────────────────────────────────────────────

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip non-GET requests (forms, API writes)
    if (event.request.method !== 'GET') return;

    // Skip browser extensions, chrome-extension URLs, etc.
    if (!url.protocol.startsWith('http')) return;

    // API requests: stale-while-revalidate
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/search/query')) {
        event.respondWith(staleWhileRevalidate(event.request, API_CACHE));
        return;
    }

    // Bible reader chapters: cache-first with background update
    if (url.pathname.startsWith('/read/')) {
        event.respondWith(staleWhileRevalidate(event.request, CHAPTERS_CACHE));
        return;
    }

    // Static assets (JS, CSS, images, fonts): cache-first
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(event.request, STATIC_CACHE));
        return;
    }

    // Pages: stale-while-revalidate
    if (event.request.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(networkFirstWithOfflineFallback(event.request, PAGES_CACHE));
        return;
    }

    // Default: network with cache fallback
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});

// ── Cache Strategies ────────────────────────────────────────────

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Offline', { status: 503 });
    }
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const networkPromise = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch(() => cached);

    return cached || networkPromise;
}

async function networkFirstWithOfflineFallback(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;

        // Return offline page
        const offlineResponse = await caches.match('/offline');
        if (offlineResponse) return offlineResponse;

        return new Response('You are offline', {
            status: 503,
            headers: { 'Content-Type': 'text/html' },
        });
    }
}

// ── Helpers ─────────────────────────────────────────────────────

function isStaticAsset(pathname) {
    return /\.(js|css|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)(\?.*)?$/.test(pathname)
        || pathname.startsWith('/build/');
}

// ── Background Sync ─────────────────────────────────────────────

self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-annotations') {
        event.waitUntil(syncAnnotationsFromIDB());
    }
});

async function syncAnnotationsFromIDB() {
    const DB_NAME = 'HisWord-sync';
    const DB_VERSION = 1;
    const QUEUE_STORE = 'sync-queue';

    try {
        const db = await new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = () => {
                const _db = req.result;
                if (!_db.objectStoreNames.contains(QUEUE_STORE)) {
                    _db.createObjectStore(QUEUE_STORE, { keyPath: 'id', autoIncrement: true });
                }
                if (!_db.objectStoreNames.contains('sync-meta')) {
                    _db.createObjectStore('sync-meta', { keyPath: 'key' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });

        // Read all pending changes
        const changes = await new Promise((resolve, reject) => {
            const tx = db.transaction(QUEUE_STORE, 'readonly');
            const req = tx.objectStore(QUEUE_STORE).getAll();
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });

        if (!changes || changes.length === 0) {
            db.close();
            return;
        }

        // Push to server
        const response = await fetch('/api/v1/sync/push', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                device_id: 'sw-' + (await getStoredDeviceId()),
                changes: changes.map(({ entity_type, entity_id, operation, data, timestamp }) => ({
                    entity_type,
                    entity_id,
                    operation,
                    data: data || {},
                    vector_clock: {},
                    timestamp,
                })),
            }),
        });

        if (response.ok) {
            // Clear synced items
            const tx = db.transaction(QUEUE_STORE, 'readwrite');
            const store = tx.objectStore(QUEUE_STORE);
            for (const change of changes) {
                if (change.id !== undefined) store.delete(change.id);
            }
            await new Promise((resolve) => { tx.oncomplete = resolve; });

            // Notify clients
            const clients = await self.clients.matchAll({ type: 'window' });
            for (const client of clients) {
                client.postMessage({ type: 'sync-complete', synced: changes.length });
            }
            console.log('[SW] Background sync: pushed', changes.length, 'changes');
        } else {
            console.warn('[SW] Background sync: server returned', response.status);
            throw new Error('Sync push failed');
        }

        db.close();
    } catch (err) {
        console.error('[SW] Background sync failed, will retry', err);
        throw err; // Re-throw so the browser retries the sync
    }
}

async function getStoredDeviceId() {
    try {
        const db = await new Promise((resolve, reject) => {
            const req = indexedDB.open('HisWord-sync', 1);
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
        const tx = db.transaction('sync-meta', 'readonly');
        const result = await new Promise((resolve) => {
            const req = tx.objectStore('sync-meta').get('device-id');
            req.onsuccess = () => resolve(req.result?.value || 'unknown');
            req.onerror = () => resolve('unknown');
        });
        db.close();
        return result;
    } catch {
        return 'unknown';
    }
}

// ── Push Notifications (future) ─────────────────────────────────

self.addEventListener('push', (event) => {
    if (!event.data) return;

    const data = event.data.json();
    event.waitUntil(
        self.registration.showNotification(data.title || 'HisWord', {
            body: data.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            data: data.url ? { url: data.url } : undefined,
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then((clients) => {
            for (const client of clients) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
