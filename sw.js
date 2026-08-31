// Service Worker — Porta Retrato
// ---------------------------------------------------------------------------
// Objetivo: que el hosting solo tenga que enviar cada foto/video UNA vez.
// Todo lo que cuelgue de /uploads/ se guarda en Cache Storage (disco del
// dispositivo, sobrevive reinicios y cierres del navegador). El resto del
// tráfico (HTML, JS, CSS, API JSON, clima) sigue yendo a la red normalmente.
//
// El Visor (viewer.js) se encarga de:
//   1. Pedir cada archivo del álbum activo una vez (precarga) → el SW lo baja
//      y lo cachea.
//   2. Mandar un mensaje PRUNE_MEDIA con la lista vigente → el SW borra del
//      cache lo que ya no está en el álbum.

const MEDIA_CACHE = 'pr-media-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter(n => n !== MEDIA_CACHE).map(n => caches.delete(n)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method === 'GET' &&
        url.origin === self.location.origin &&
        url.pathname.includes('/uploads/')) {
        event.respondWith(serveMedia(event.request));
    }
    // Cualquier otra petición: comportamiento por defecto (red).
});

async function serveMedia(request) {
    const cache = await caches.open(MEDIA_CACHE);
    // La clave incluye el ?v=<mtime>: si el archivo se reemplazó en el servidor
    // (p.ej. una foto rotada), la URL cambia y se vuelve a descargar una vez.
    const key = request.url;

    const cached = await cache.match(key);
    if (cached) return cached;

    try {
        // Se pide el archivo COMPLETO (sin cabecera Range) para guardarlo entero.
        const netResp = await fetch(key, { cache: 'no-store' });
        if (netResp && netResp.status === 200) {
            await cache.put(key, netResp.clone());
        }
        return netResp; // 200 recién bajado, o 404/5xx → lo maneja el Visor (onerror salta al siguiente)
    } catch (e) {
        return new Response('', { status: 504, statusText: 'Sin conexion y sin cache' });
    }
}
// Nota: se devuelve siempre la respuesta 200 completa, también a los <video>
// que piden por Range. El navegador reproduce igual (buffer desde el inicio) y
// evitamos cargar 200+ MB en memoria por cada seek en un equipo modesto. El
// portarretrato reproduce los clips de corrido, no hace seeking.

self.addEventListener('message', (event) => {
    const data = event.data || {};
    if (data.type === 'PRUNE_MEDIA' && Array.isArray(data.keep)) {
        event.waitUntil((async () => {
            const cache = await caches.open(MEDIA_CACHE);
            const keep = new Set(data.keep.map(u => new URL(u, self.location.origin).href));
            const keys = await cache.keys();
            await Promise.all(keys.map(k => keep.has(k.url) ? Promise.resolve() : cache.delete(k)));
            if (event.source) event.source.postMessage({ type: 'PRUNE_DONE', kept: keep.size });
        })());
    }
});
