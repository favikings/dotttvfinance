// DOTT TV Finance — service worker (Tech Spec §17).
//
// App-shell caching strategy:
//  - CACHE_VERSION is the single "bump me on every deploy" knob. Any deploy
//    that touches a static asset (CSS/JS/icons/sprite/manifest) MUST bump it,
//    or returning users keep serving the stale cached shell. Bumping it makes
//    install() create a fresh cache and activate() prune every old one.
//  - install() pre-caches the app shell: entry HTML, compiled CSS/JS, icons,
//    sprite, manifest, fonts, and the self-hosted SweetAlert2/Alpine.js
//    bundles — everything is same-origin, so the shell works fully offline
//    once installed with no cross-origin dependency at all.
//  - fetch() intercepts GET requests ONLY:
//      * navigations (HTML pages, including report pages) use a network-first
//        strategy — online always serves fresh, offline falls back to the
//        last-cached copy. This is the sanctioned "view a stale report
//        offline" case, paired with the visible "last synced" indicator.
//      * same-origin static assets use cache-first.
//      * every POST/PUT/DELETE (approve, reject, store, delete, settings
//        saves...) passes through UNTOUCHED — financial writes never queue
//        offline. With no connection the fetch rejects and the client shows
//        a clear "you're offline" state instead of silently dropping the write.
//  - Push/notification handling (Tech Spec §15a) lives below — additive
//    listeners, no shared state with the caching logic.

const CACHE_VERSION = 'v2';
const CACHE_PREFIX = 'dotttv-';
const SHELL_CACHE = CACHE_PREFIX + 'shell-' + CACHE_VERSION;
const RUNTIME_CACHE = CACHE_PREFIX + 'runtime-' + CACHE_VERSION;

// App-shell assets, pre-cached at install. All paths are resolved against the
// service worker's registration scope, so they work under both a root deploy
// and the /dotttvfinance subfolder deploy. Every asset is same-origin —
// SweetAlert2, Alpine.js and Inter are all self-hosted, mirroring the exact
// tags in app/views/layouts/app.php.
const SHELL_ASSETS = [
    './',                                           // entry HTML shell
    'assets/css/app.css',
    'assets/js/app.js',
    'assets/js/alpine.min.js',
    'assets/js/sweetalert2.min.js',
    'assets/fonts/inter-400.woff2',
    'assets/fonts/inter-500.woff2',
    'assets/fonts/inter-600.woff2',
    'assets/icons/icon-192.png',
    'assets/icons/icon-512.png',
    'assets/icons/icon-512-maskable.png',
    'assets/icons/sprite.svg',
    'manifest.json',
];

// ---------------------------------------------------------------------------
// Install / activate
// ---------------------------------------------------------------------------

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(SHELL_CACHE);
            // allSettled: a single unreachable asset (e.g. a flaky CDN) must
            // not fail the whole install. Runtime cache-first backfills any
            // miss on the next online load anyway.
            await Promise.allSettled(
                SHELL_ASSETS.map((asset) =>
                    cache.add(new Request(new URL(asset, self.registration.scope)))
                )
            );
            await self.skipWaiting();
        })()
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(
                keys
                    .filter((name) => name.startsWith(CACHE_PREFIX) && !name.endsWith(CACHE_VERSION))
                    .map((name) => caches.delete(name))
            );
            await self.clients.claim();
        })()
    );
});

// ---------------------------------------------------------------------------
// Fetch — the GET-only interception (Tech Spec §17)
// ---------------------------------------------------------------------------

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Mutating requests are NEVER intercepted or queued. They hit the network
    // directly; offline, the browser rejects the fetch and the page's own
    // error handling shows the "you're offline" state. This is the whole
    // point: a stale read is safe to serve, a stale write is not.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    const isSameOrigin = url.origin === self.location.origin;

    if (!isSameOrigin) {
        return; // e.g. any future third-party integration — not our shell
    }

    // PDF/Excel export routes are generated report artifacts, not app shell —
    // never cache them (a duplicate snapshot of report data we'd have to
    // evict, for no offline benefit).
    if (url.pathname.endsWith('/pdf') || url.pathname.endsWith('/excel')) {
        return;
    }

    // HTML navigations (pages, incl. reports): network-first so the user
    // always sees live data online, and a cached copy when offline.
    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    // Everything else GET + same-origin: cache-first.
    event.respondWith(cacheFirst(request));
});

async function networkFirstNavigation(request) {
    const cache = await caches.open(RUNTIME_CACHE);

    try {
        const response = await fetch(request);
        if (response && response.ok) {
            await cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        // Offline — serve the last-cached copy of this exact page (the
        // offline report-viewing case), or the shell entry as a last resort.
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }
        const shell = await caches.match(new URL('./', self.registration.scope));
        if (shell) {
            return shell;
        }
        throw err;
    }
}

// Same-origin static assets are cached under a query-stripped key so the
// pre-cached shell (e.g. assets/css/app.css) is matched by the runtime
// request even when it carries a ?v=... cache-buster. Freshness after a
// deploy is guaranteed by bumping CACHE_VERSION — a new version means a new
// cache, so nothing stale is ever served.
async function cacheFirst(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    const key = cacheKey(request);

    // caches.match() (not cache.match) so the pre-cached shell entries in
    // SHELL_CACHE are found too — the very first offline session works.
    const cached = await caches.match(key);
    if (cached) {
        return cached;
    }

    const response = await fetch(request);
    if (response && response.ok) {
        await cache.put(key, response.clone());
    }
    return response;
}

function cacheKey(request) {
    const url = new URL(request.url);
    url.search = '';
    return new Request(url);
}

// ---------------------------------------------------------------------------
// Web Push (Tech Spec §15a) — a nudge with a deep link only, never an action
// button that approves/rejects directly — tapping it opens the app, where
// the normal Permission::require()-gated flow takes over. Additive to the
// app-shell caching logic above; no shared state with those handlers.
// ---------------------------------------------------------------------------

self.addEventListener('push', (event) => {
    let data = { title: 'DOTT TV Finance', body: '', url: '/' };
    if (event.data) {
        try {
            data = { ...data, ...event.data.json() };
        } catch (err) {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: 'assets/icons/icon-192.png',
            badge: 'assets/icons/icon-192.png',
            data: { url: data.url },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                const clientPath = new URL(client.url).pathname;
                const targetPath = new URL(targetUrl, self.location.origin).pathname;
                if (clientPath === targetPath && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clientList.length > 0 && 'focus' in clientList[0]) {
                return clientList[0].focus().then((client) => client.navigate(targetUrl));
            }
            return self.clients.openWindow(targetUrl);
        })
    );
});