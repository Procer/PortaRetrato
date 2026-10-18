// Service Worker retirado.
// ---------------------------------------------------------------------------
// La app ahora corre en la PC del portarretrato (Laragon) y la media se lee del
// disco local, así que ya no hace falta cachear nada. Este archivo queda como
// "lápida": cualquier navegador que todavía tenga registrado el SW viejo lo
// actualiza a esta versión, que borra su cache y se desregistra sola.

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.map(k => caches.delete(k)));
        await self.registration.unregister();
        const clients = await self.clients.matchAll({ type: 'window' });
        clients.forEach(c => c.navigate(c.url));
    })());
});
