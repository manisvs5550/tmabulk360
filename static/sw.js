// TMA Operations 360 — Service Worker
// Caches shell + static assets for offline maritime use

const CACHE_NAME = 'tmaops360-v5';
const SHELL_ASSETS = [
    '/',
    '/login',
    '/offline',
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
                    return cached || caches.match('/offline');
                })
            )
    );
});

// Background sync: retry queued form submissions when connectivity returns
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-contact-form') {
        event.waitUntil(syncQueuedForms());
    }
});

async function syncQueuedForms() {
    const cache = await caches.open('tmaops360-formqueue');
    const requests = await cache.keys();
    for (const request of requests) {
        try {
            const response = await cache.match(request);
            const body = await response.text();
            await fetch('/contact', {
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
