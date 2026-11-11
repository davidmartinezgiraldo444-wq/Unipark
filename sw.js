// UniPark · Service worker: muestra los avisos push (pago pendiente) aunque la página esté cerrada.
// No guarda páginas ni datos en caché.
'use strict';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
  let datos = {};
  try { datos = event.data ? event.data.json() : {}; } catch (e) { datos = { cuerpo: event.data ? event.data.text() : '' }; }
  const titulo = datos.titulo || 'UniPark';
  event.waitUntil(self.registration.showNotification(titulo, {
    body: datos.cuerpo || '',
    tag: datos.etiqueta || 'unipark',
    renotify: true,
    requireInteraction: true,
    icon: new URL('assets/img/icono-192.png', self.registration.scope).href,
    badge: new URL('assets/img/icono-192.png', self.registration.scope).href,
    data: { url: new URL(datos.url || 'mi-cuenta', self.registration.scope).href }
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const destino = event.notification.data && event.notification.data.url;
  event.waitUntil((async () => {
    const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const v of ventanas) {
      if (v.url.startsWith(self.registration.scope)) {
        await v.focus();
        if (destino) v.navigate(destino);
        return;
      }
    }
    if (destino) await self.clients.openWindow(destino);
  })());
});
