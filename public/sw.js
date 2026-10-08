/**
 * Service worker mínimo para habilitar la instalación como PWA.
 * Estrategia: network-first con respaldo en caché (apta para maquetación).
 * En producción conviene definir una lista de precache y versionado.
 */

const CACHE = 'ciberamenaza-v1';
const OFFLINE_FALLBACK = '/';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.add(OFFLINE_FALLBACK)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((claves) =>
            Promise.all(claves.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request)
            .then((respuesta) => {
                const copia = respuesta.clone();
                caches.open(CACHE).then((c) => c.put(event.request, copia)).catch(() => {});
                return respuesta;
            })
            .catch(() => caches.match(event.request).then((r) => r || caches.match(OFFLINE_FALLBACK)))
    );
});
