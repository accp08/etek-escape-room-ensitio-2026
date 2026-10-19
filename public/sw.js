/**
 * Service worker mínimo para habilitar la instalación como PWA.
 *
 * Solo guarda en caché archivos estáticos (CSS, JS, íconos, fuentes). Las
 * páginas del juego NUNCA se cachean: llevan la sesión del jugador, y una
 * copia guardada podría mostrarle a alguien la partida de otro.
 *
 * Cambiar CACHE borra en los celulares todo lo que guardó la versión anterior.
 */

const CACHE = 'ciberamenaza-v2';

const ESTATICO = /^\/(build|icons|marca|fonts)\//;

self.addEventListener('install', () => {
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
    const peticion = event.request;
    if (peticion.method !== 'GET') return;
    // Audio y video piden rangos (respuestas 206): que los maneje el navegador.
    if (peticion.headers.has('range')) return;

    const url = new URL(peticion.url);

    // Páginas y todo lo demás: directo a la red, sin guardar nada.
    if (url.origin !== self.location.origin || !ESTATICO.test(url.pathname)) return;

    event.respondWith(
        fetch(peticion)
            .then((respuesta) => {
                if (respuesta.ok) {
                    const copia = respuesta.clone();
                    caches.open(CACHE).then((c) => c.put(peticion, copia)).catch(() => {});
                }
                return respuesta;
            })
            .catch(() => caches.match(peticion))
    );
});
