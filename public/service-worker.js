/*
 * DriverHub service worker.
 *
 * The app shell is pre-cached so the page opens without a network, and static
 * build assets are cached as they are requested. API calls are never cached —
 * showing a stale applicant list or an old subscription state would be worse
 * than showing nothing.
 */

const VERSION = 'driverhub-v1';
const SHELL = ['/', '/offline.html'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSION)
            .then((cache) => cache.addAll(SHELL))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Same-origin only; never touch the API.
    if (url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/api/')) return;

    // Navigations: network first, fall back to the cached shell when offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(VERSION).then((cache) => cache.put('/', copy));

                    return response;
                })
                .catch(() => caches.match('/').then((cached) => cached || caches.match('/offline.html')))
        );

        return;
    }

    // Build assets are content-hashed, so a cache hit is always correct.
    event.respondWith(
        caches.match(request).then((cached) => cached || fetch(request).then((response) => {
            if (response.ok && (url.pathname.startsWith('/js/') || url.pathname.startsWith('/css/') || url.pathname.startsWith('/icons/'))) {
                const copy = response.clone();
                caches.open(VERSION).then((cache) => cache.put(request, copy));
            }

            return response;
        }))
    );
});
