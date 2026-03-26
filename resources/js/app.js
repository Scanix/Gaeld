import { createApp, h } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import '../css/app.css'

// ── Page transition progress bar ──
let progressBar = null
let progressTimeout = null

function showProgress() {
  if (progressBar) return
  progressBar = document.createElement('div')
  progressBar.className = 'inertia-progress'
  document.body.appendChild(progressBar)
  // Force reflow then animate
  progressBar.offsetWidth // eslint-disable-line no-unused-expressions
  progressBar.style.width = '80%'
}

function hideProgress() {
  if (!progressBar) return
  progressBar.style.width = '100%'
  progressBar.style.opacity = '0'
  setTimeout(() => {
    progressBar?.remove()
    progressBar = null
  }, 200)
}

router.on('start', () => {
  progressTimeout = setTimeout(showProgress, 100)
})

router.on('finish', () => {
  clearTimeout(progressTimeout)
  hideProgress()
})

createInertiaApp({
  title: (title) => title ? `${title} — Gäld` : 'Gäld',
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.vue`,
      import.meta.glob('./Pages/**/*.vue'),
    ),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
