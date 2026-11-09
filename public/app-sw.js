/* Service worker do app de check-in: guarda só os arquivos fixos (CSS, JS, ícones) para abrir rápido.
   As páginas e o registro de entrada vão sempre ao servidor (o check-in precisa da confirmação na hora). */
const CACHE = 'ff-checkin-v2';
const FIXOS = ['/css/checkin.css', '/js/vendor/jsQR.js', '/app-icons/icone-192.png'];
self.addEventListener('install', (e) => { e.waitUntil(caches.open(CACHE).then((c) => c.addAll(FIXOS)).then(() => self.skipWaiting())); });
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const u = new URL(e.request.url);
  if (e.request.method !== 'GET' || u.origin !== location.origin) return;
  if (FIXOS.some((f) => u.pathname === f)) {
    // rede primeiro (pega sempre a versão nova do site); sem internet, usa a cópia guardada
    e.respondWith(fetch(e.request).then((r) => { const copia = r.clone(); caches.open(CACHE).then((c) => c.put(u.pathname, copia)); return r; })
      .catch(() => caches.match(u.pathname)));
  }
});
