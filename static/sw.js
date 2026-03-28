// TMA Operations 360 — Service Worker
// Caches shell + static assets for offline maritime use

const CACHE_NAME = 'tmaops360-v7';
const SHELL_ASSETS = [
    '/',
    '/login.php',
    '/offline.php',
    '/inventory.php',
    '/static/css/style.css',
    '/static/css/dashboard.css',
    '/static/js/main.js',
    '/static/images/fleet-background.webp',
    '/static/images/logo.svg',
    '/static/manifest.json',
];

// Install: pre-cache app shell
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// Fetch: network-first for pages, cache-first for static assets
self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Skip non-GET requests (form POSTs handled separately)
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Static assets — cache-first
    if (url.pathname.startsWith('/static/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    return response;
                });
            })
        );
        return;
    }

    // HTML pages — network-first, fall back to cache, then offline page
    event.respondWith(
        fetch(request)
            .then((response) => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                return response;
            })
            .catch(() =>
                caches.match(request).then((cached) => {
                    return cached || caches.match('/offline.php');
                })
            )
    );
});

// Background sync: retry queued form submissions when connectivity returns
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-contact-form') {
        event.waitUntil(syncQueuedForms());
    }
    if (event.tag === 'sync-inventory') {
        event.waitUntil(syncInventoryQueue());
    }
});

async function syncQueuedForms() {
    const cache = await caches.open('tmaops360-formqueue');
    const requests = await cache.keys();
    for (const request of requests) {
        try {
            const response = await cache.match(request);
            const body = await response.text();
            await fetch('/contact.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
            });
            await cache.delete(request);
        } catch (e) {
            // Still offline — will retry on next sync
            break;
        }
    }
}

// Inventory offline sync via IndexedDB
async function syncInventoryQueue() {
    const INV_DB_NAME = 'tmaops360_inventory';
    const INV_STORE = 'pending_submissions';

    const db = await new Promise((resolve, reject) => {
        const req = indexedDB.open(INV_DB_NAME, 1);
        req.onupgradeneeded = () => {
            const d = req.result;
            if (!d.objectStoreNames.contains(INV_STORE)) {
                d.createObjectStore(INV_STORE, { keyPath: 'id', autoIncrement: true });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });

    const entries = await new Promise((resolve) => {
        const tx = db.transaction(INV_STORE, 'readonly');
        const req = tx.objectStore(INV_STORE).getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => resolve([]);
    });

    for (const entry of entries) {
        try {
            const resp = await fetch('/inventory_sync.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(entry.payload),
                credentials: 'same-origin',
            });
            if (resp.ok) {
                await new Promise((resolve) => {
                    const tx = db.transaction(INV_STORE, 'readwrite');
                    tx.objectStore(INV_STORE).delete(entry.id);
                    tx.oncomplete = () => resolve();
                    tx.onerror = () => resolve();
                });
            }
        } catch {
            break;
        }
    }
}
