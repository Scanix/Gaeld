/**
 * Gäld Service Worker
 *
 * Strategy:
 *  - CacheFirst for versioned Vite build assets (/build/**)
 *    They carry content-hashes in filenames, so we can cache indefinitely.
 *  - NetworkFirst for all navigation requests (Inertia/Laravel routes).
 *    Falls back to the cached app-shell HTML when offline.
 *  - Passthrough for cross-origin requests.
 *
 * Cache version: bump CACHE_NAME when the shell structure changes significantly.
 */

const CACHE_NAME = 'gaeld-shell-v1'

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

  // NetworkFirst — navigation requests (Inertia routes served by Laravel)
  // Falls back to the cached app-shell so the app loads without a connection.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() =>
        caches.match('/').then(
          (cached) =>
            cached ||
            new Response(
              '<!doctype html><html><head><meta charset="utf-8"><title>Gäld — Offline</title></head><body style="font-family:sans-serif;padding:2rem"><h1>You are offline</h1><p>Please check your connection and try again.</p></body></html>',
              { headers: { 'Content-Type': 'text/html' } }
            )
        )
      )
    )
  }
})
