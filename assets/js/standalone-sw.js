const swUrl = new URL(self.location.href);
const CACHE_VERSION = 'agp-pv-shell-v' + (swUrl.searchParams.get('ver') || '1');
const OFFLINE_PATH = swUrl.searchParams.get('shell') || '/post-venta/';
const ASSET_PREFIX = swUrl.searchParams.get('assetPrefix') || '/wp-content/plugins/agrocampo-post-venta/assets/';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll([OFFLINE_PATH]).catch(() => undefined))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((key) => key.indexOf('agp-pv-shell-v') === 0 && key !== CACHE_VERSION)
                .map((key) => caches.delete(key))
        ))
    );
    self.clients.claim();
});

function shouldBypass(requestUrl, method) {
    if (method !== 'GET') {
        return true;
    }

    if (requestUrl.origin !== self.location.origin) {
        return true;
    }

    if (requestUrl.searchParams.has('agp_pv_manifest')) {
        return true;
    }

    if (requestUrl.pathname.indexOf('/wp-admin/') !== -1) {
        return true;
    }

    if (requestUrl.pathname.indexOf('/wp-login.php') !== -1) {
        return true;
    }

    if (requestUrl.pathname.indexOf('/wp-admin/admin-ajax.php') !== -1) {
        return true;
    }

    return false;
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const requestUrl = new URL(request.url);

    if (shouldBypass(requestUrl, request.method)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const contentType = (response.headers.get('content-type') || '').toLowerCase();
                    if (response.ok && contentType.indexOf('text/html') !== -1) {
                        const clone = response.clone();
                        caches.open(CACHE_VERSION).then((cache) => cache.put(OFFLINE_PATH, clone)).catch(() => undefined);
                    }
                    return response;
                })
                .catch(() => caches.match(OFFLINE_PATH).then((cached) => cached || Response.error()))
        );
        return;
    }

    const isStaticShellAsset = requestUrl.pathname.indexOf(ASSET_PREFIX) === 0;
    if (!isStaticShellAsset) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) {
                fetch(request)
                    .then((response) => {
                        if (response && response.ok) {
                            caches.open(CACHE_VERSION).then((cache) => cache.put(request, response.clone())).catch(() => undefined);
                        }
                    })
                    .catch(() => undefined);

                return cached;
            }

            return fetch(request).then((response) => {
                if (response && response.ok) {
                    caches.open(CACHE_VERSION).then((cache) => cache.put(request, response.clone())).catch(() => undefined);
                }
                return response;
            });
        })
    );
});


self.addEventListener('message', (event) => {
    if (!event || !event.data || event.data.type !== 'SKIP_WAITING') {
        return;
    }

    self.skipWaiting();
});
