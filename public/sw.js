'use strict';
const CACHE = 'mazzuco-public-v1';
const OFFLINE = '/offline.html';
// Fixed public files only. No runtime caching of HTML, API, uploads or customer data.
const ASSETS = [OFFLINE, '/assets/icons/app-192.png', '/assets/icons/app-512.png', '/assets/icons/app-maskable-512.png', '/assets/icons/apple-touch-icon.png'];
self.addEventListener('install', event => {
 event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(ASSETS)));
 // Updates wait until previous tabs close, avoiding a reload during checkout.
});
self.addEventListener('activate', event => {
 event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('mazzuco-public-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
 const request = event.request;
 const url = new URL(request.url);
 if (request.method !== 'GET' || url.origin !== self.location.origin) return;
 if (request.mode === 'navigate') {
  event.respondWith(fetch(request).catch(async () => {
   const fallback = await caches.match(OFFLINE);
   return fallback || new Response('Sem conexão. Reconecte para acessar a Mazzuco.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
  }));
  return;
 }
 if (url.search || !ASSETS.includes(url.pathname)) return;
 event.respondWith(caches.match(request).then(cached => cached || fetch(request)));
});
