const CACHE_NAME = 'jpos-tablet-cache-v1';
const STATIC_ASSETS = [
  '/tablet/index.html',
  '/tablet/app.js',
  '/tablet/manifest.json',
  '/vendor/alpine-3.15.12.min.js',
  '/vendor/tailwind-play-3.4.17.js',
  '/vendor/jpos.css',
  '/vendor/jpos-number.js',
  '/vendor/jpos-pemindai.js',
  '/images/logo-jpos.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch((err) => {
        console.warn('Gagal mem-precache beberapa asset:', err);
      });
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Jangan cache permintaan API ke server
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request).catch(() => {
        return new Response(JSON.stringify({ offline: true, error: 'Server tidak terjangkau' }), {
          headers: { 'Content-Type': 'application/json' }
        });
      })
    );
    return;
  }

  // Cache-first untuk file statis aplikasi
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) {
        // Update di latar belakang (stale-while-revalidate)
        fetch(event.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkResponse));
          }
        }).catch(() => {});
        return cached;
      }
      return fetch(event.request).catch(() => {
        // Fallback jika offline dan buka halaman utama
        if (event.request.mode === 'navigate') {
          return caches.match('/tablet/index.html');
        }
      });
    })
  );
});
