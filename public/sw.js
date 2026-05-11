/**
 * NexusPHP service worker.
 *
 * Scope: cache only the manifest, icons, and the offline shell. We
 * deliberately do NOT intercept tracker / Livewire requests — they
 * must always hit the network so peer state, promotions, and
 * announce semantics stay correct.
 */

const VERSION = 'nexus-sw-v1';
const SHELL_CACHE = `nexus-shell-${VERSION}`;
const SHELL_ASSETS = [
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_ASSETS)).catch(() => {}),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((names) =>
                Promise.all(
                    names
                        .filter((n) => n.startsWith('nexus-shell-') && n !== SHELL_CACHE)
                        .map((n) => caches.delete(n)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (!SHELL_ASSETS.includes(url.pathname)) return;

    event.respondWith(
        caches.match(request).then(
            (cached) =>
                cached ||
                fetch(request).then((res) => {
                    if (res.ok) {
                        const clone = res.clone();
                        caches.open(SHELL_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return res;
                }),
        ),
    );
});

/**
 * Web push handler. Payload is JSON:
 *   { title, body, url?, tag?, icon? }
 * Falls back to a generic notification if the payload is missing.
 */
self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'NexusPHP', body: event.data ? event.data.text() : '' };
    }
    const title = payload.title || 'NexusPHP';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: payload.tag,
        data: { url: payload.url || '/' },
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client && new URL(client.url).pathname === new URL(url, self.location.origin).pathname) {
                    return client.focus();
                }
            }
            return self.clients.openWindow ? self.clients.openWindow(url) : null;
        }),
    );
});
