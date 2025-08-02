// Service Worker for ImmuCare PWA
const CACHE_VERSION = '1.0.3';
const CACHE_NAME = `immucare-v${CACHE_VERSION}`;
const urlsToCache = [
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/index.html',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/manifest.json',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/styles.css',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/script.js',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/images/icon-192x192.png',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/images/icon-512x512.png',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/images/favicon-16x16.png',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/images/favicon-32x32.png',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/images/favicon.svg',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/images/apple-touch-icon.png',
  'https://aphid-major-dolphin.ngrok-free.app/mic_new/images/logo.png'
];

const CHECK_INTERVAL = 15 * 60 * 1000; // 15 minutes

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    Promise.all([
      self.clients.claim(),
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME && cacheName.startsWith('immucare-')) {
              return caches.delete(cacheName);
            }
          })
        );
      })
    ])
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (event.request.mode === 'navigate' || 
            (event.request.method === 'GET' && event.request.headers.get('accept').includes('text/html'))) {
          return fetch(event.request)
            .then(networkResponse => {
              const responseToCache = networkResponse.clone();
              caches.open(CACHE_NAME).then(cache => {
                cache.put(event.request, responseToCache);
              });
              return networkResponse;
            })
            .catch(() => {
              return response || new Response('<h1>Offline</h1><p>You are offline and this page is not available.</p>', { headers: { 'Content-Type': 'text/html' } });
            });
        }
        if (response) {
          return response;
        }
        return fetch(event.request)
          .then(response => {
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            const responseToCache = response.clone();
            if (event.request.method === 'GET' && !event.request.url.includes('/api/')) {
              caches.open(CACHE_NAME)
                .then(cache => {
                  cache.put(event.request, responseToCache);
                });
            }
            return response;
          })
          .catch(() => {
            if (event.request.mode === 'navigate') {
              return new Response('<h1>Offline</h1><p>You are offline and this page is not available.</p>', { headers: { 'Content-Type': 'text/html' } });
            }
            if (event.request.destination === 'image') {
              return new Response('', {
                status: 200,
                statusText: 'OK'
              });
            }
            return new Response('Network error occurred', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: new Headers({
                'Content-Type': 'text/plain'
              })
            });
          });
      })
  );
});

self.addEventListener('message', event => {
  if (event.data) {
    if (event.data.type === 'CHECK_UPDATE') {
      self.registration.update()
        .then(() => {
          event.source.postMessage({
            type: 'UPDATE_CHECK_COMPLETED'
          });
        });
    }
    if (event.data.type === 'SKIP_WAITING') {
      self.skipWaiting();
    }
  }
});

const checkForUpdates = async () => {
  try {
    await self.registration.update();
  } catch (error) {}
};
setTimeout(checkForUpdates, 5000);
setInterval(checkForUpdates, CHECK_INTERVAL); 