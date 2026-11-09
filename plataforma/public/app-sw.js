/* Service worker do app de check-in: guarda só os arquivos fixos (CSS, JS, ícones) para abrir rápido.
   As páginas e o registro de entrada vão sempre ao servidor (o check-in precisa da confirmação na hora). */
const CACHE = 'ff-checkin-v1';
const FIXOS = ['/css/checkin.css', '/js/vendor/jsQR.js', '/app-icons/icone-192.png'];
self.addEventListener('install', (e) => { e.waitUntil(caches.open(CACHE).then((c) => c.addAll(FIXOS)).then(() => self.skipWaiting())); });
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const u = new URL(e.request.url);
  if (e.request.method !== 'GET' || u.origin !== location.origin) return;
  if (FIXOS.some((f) => u.pathname === f)) {
    e.respondWith(caches.match(e.request, { ignoreSearch: true }).then((r) => r || fetch(e.request)));
  }
});
