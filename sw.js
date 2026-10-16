/* =====================================================
   monchomania - Service Worker (app instalable)
   Solo guarda en caché los recursos estáticos (iconos, CSS
   y JS). Las páginas PHP siempre se piden al servidor para
   no mostrar contenido antiguo; si la red falla, se muestra
   offline.html en lugar del error del navegador.
   ===================================================== */

var CACHE_NAME = 'monchomania-static-v2';
var OFFLINE_URL = './offline.html';
var STATIC_ASSETS = [
    './offline.html',
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

    // Las páginas siempre se piden al servidor (nunca se sirve HTML antiguo).
    // Si la red rechaza o corta la conexión, se muestra la pantalla de aviso
    // propia en lugar del error del navegador ("Sitio no disponible").
    if (request.mode === 'navigate' || request.destination === 'document') {
        event.respondWith(
            fetch(request).catch(function () {
                return caches.match(OFFLINE_URL).then(function (cached) {
                    return cached || Response.error();
                });
            })
        );
        return;
    }

    // El resto de respuestas del servidor no se cachean: solo estáticos de /assets.
    if (url.pathname.indexOf('/assets/') === -1) {
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