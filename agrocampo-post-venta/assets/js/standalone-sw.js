const CACHE_VERSION = 'agp-pv-shell-v1.4.78';
const OFFLINE_URL = '/post-venta/';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll([OFFLINE_URL]).catch(() => undefined))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((key) => key.indexOf('agp-pv-shell-') === 0 && key !== CACHE_VERSION)
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
                    const clone = response.clone();
                    caches.open(CACHE_VERSION).then((cache) => cache.put(OFFLINE_URL, clone)).catch(() => undefined);
                    return response;
                })
                .catch(() => caches.match(OFFLINE_URL).then((cached) => cached || Response.error()))
        );
        return;
    }

    const isStaticShellAsset = requestUrl.pathname.indexOf('/wp-content/plugins/agrocampo-post-venta/assets/') !== -1;
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
