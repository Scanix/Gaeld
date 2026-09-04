/**
 * Gäld Service Worker
 *
 * Strategy:
 *  - CacheFirst for versioned Vite build assets (/build/**)
 *    They carry content-hashes in filenames, so we can cache indefinitely.
 *  - NetworkOnly for navigation requests (Inertia/Laravel routes).
 *    HTML contains session and asset-version state, so it must never be served
 *    from an old deployment cache.
 *  - Passthrough for cross-origin requests.
 *
 * Cache version: bump when the service-worker behavior or shell structure changes.
 */

const CACHE_NAME = 'gaeld-shell-v2'

// ─── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) =>
      cache.addAll([
        '/',
        '/favicon.ico',
        '/android-chrome-192x192.png',
        '/android-chrome-512x512.png',
        '/apple-touch-icon.png',
      ]).catch(() => {
        // Non-critical — proceed even if some assets 404
      })
    )
  )
  self.skipWaiting()
})

// ─── Activate ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => key !== CACHE_NAME)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  )
})

// ─── Fetch ────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
  const { request } = event
  const url = new URL(request.url)

  // Only handle same-origin requests
  if (url.origin !== self.location.origin) return

  // CacheFirst — versioned Vite build assets are safe to cache indefinitely
  if (url.pathname.startsWith('/build/')) {
    event.respondWith(
      caches.match(request).then(
        (cached) =>
          cached ||
          fetch(request).then((response) => {
            if (response.ok) {
              const clone = response.clone()
              caches.open(CACHE_NAME).then((cache) => cache.put(request, clone))
            }
            return response
          })
      )
    )
    return
  }

  // NetworkOnly — never cache Inertia/Laravel HTML. It contains session state,
  // CSRF tokens, and the current Vite asset version.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() =>
        new Response(
          '<!doctype html><html><head><meta charset="utf-8"><title>Gäld — Offline</title></head><body style="font-family:sans-serif;padding:2rem"><h1>You are offline</h1><p>Please check your connection and try again.</p></body></html>',
          { headers: { 'Content-Type': 'text/html' }, status: 503 }
        )
      )
    )
  }
})
