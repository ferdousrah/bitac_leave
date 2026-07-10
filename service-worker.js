// BITAC Leave PWA — caches static assets only.
// Never caches PHP pages or API responses (session-authenticated / user-specific).

// Pusher Beams push-notification worker. Wrapped in try/catch so a CDN outage
// can never break our own service worker.
try {
  importScripts("https://js.pusher.com/beams/service-worker.js");
} catch (e) {
  // Pusher unreachable — push notifications disabled, caching still works.
}

// Bump on every deploy so old caches get purged automatically.
const CACHE_VERSION = 'bitac-leave-v2';
const STATIC_CACHE  = CACHE_VERSION + '-static';

const IS_LOCALHOST = /^(localhost|127\.0\.0\.1|\[::1\])(:|$)/.test(self.location.host);

self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil((async function () {
    // Localhost: actively self-unregister and purge all caches so dev never sees stale state.
    if (IS_LOCALHOST) {
      const keys = await caches.keys();
      await Promise.all(keys.map(function (k) { return caches.delete(k); }));
      await self.registration.unregister();
      const clients = await self.clients.matchAll({ type: 'window' });
      clients.forEach(function (c) { try { c.navigate(c.url); } catch (e) {} });
      return;
    }
    // Production: purge any prior-version caches.
    const keys = await caches.keys();
    await Promise.all(
      keys.filter(function (k) { return k.indexOf('bitac-leave-') === 0 && !k.startsWith(CACHE_VERSION); })
          .map(function (k) { return caches.delete(k); })
    );
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', function (event) {
  // Localhost: never intercept — let browser hit the server directly.
  if (IS_LOCALHOST) return;
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.endsWith('.php') || url.pathname.includes('/api/')) return;
  if (!/\.(css|js|png|jpg|jpeg|svg|webp|gif|woff2?|ttf|eot|ico)$/i.test(url.pathname)) return;

  event.respondWith(staleWhileRevalidate(event.request));
});

async function staleWhileRevalidate(request) {
  const cache = await caches.open(STATIC_CACHE);
  const cached = await cache.match(request);

  const networkFetch = fetch(request).then(function (response) {
    if (response && response.status === 200 && response.type === 'basic') {
      cache.put(request, response.clone()).catch(function () {});
    }
    return response;
  }).catch(function () { return null; });

  if (cached) {
    // Background revalidate; ignore failure.
    networkFetch.catch(function () {});
    return cached;
  }
  const fresh = await networkFetch;
  if (fresh) return fresh;

  // Genuine network failure — return a real network-error response (NOT a fake 504).
  // The browser shows this as a true network failure in DevTools, not a misleading 504 status.
  return Response.error();
}
