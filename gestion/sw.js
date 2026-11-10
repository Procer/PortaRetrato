// Service worker mínimo: solo existe para que el navegador permita instalar Gestión como app.
// No guarda nada en caché: todo se pide siempre al portarretrato.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
