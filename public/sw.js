/**
 * Gäld Service Worker retirement shim.
 *
 * Older releases cached HTML and Vite assets, which could leave a browser with
 * a stale Inertia shell after deployment. New releases unregister old workers
 * and remove their caches from the application shell.
 */

// Allow browsers that still have an older registration to update once and
// remove every Gäld shell cache before unregistering themselves.
self.addEventListener('install', (event) => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.indexOf('gaeld-shell-') === 0)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.registration.unregister())
      .then(() => self.clients.claim())
  )
})
