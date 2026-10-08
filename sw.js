/* =====================================================
   monchomania - Service Worker (app instalable)
   Solo guarda en caché los recursos estáticos (iconos, CSS
   y JS). Las páginas PHP siempre se piden al servidor para
   no mostrar contenido antiguo.
   ===================================================== */

var CACHE_NAME = 'monchomania-static-v1';
var STATIC_ASSETS = [
    './assets/img/favicon.png',
    './assets/img/icon-192.png',
    './assets/img/icon-512.png',
    './assets/img/logo.png',
    './assets/img/avatar-default.svg'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function (cache) { return cache.addAll(STATIC_ASSETS); })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (keys) {
                return Promise.all(keys.map(function (key) {
                    return key === CACHE_NAME ? null : caches.delete(key);
                }));
            })
            .then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    var url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Nunca cachear HTML ni respuestas del servidor: solo estáticos de /assets.
    if (request.destination === 'document' || url.pathname.indexOf('/assets/') === -1) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.match(request).then(function (cached) {
                var network = fetch(request).then(function (response) {
                    if (response && response.status === 200) {
                        cache.put(request, response.clone());
                    }
                    return response;
                }).catch(function () {
                    return cached;
                });

                return cached || network;
            });
        })
    );
});