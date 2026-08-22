import { defineConfig, loadEnv } from 'vite'
import { existsSync, readFileSync, readdirSync } from 'fs'
import laravel from 'laravel-vite-plugin'
import { join, relative, resolve } from 'path'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { sentryVitePlugin } from '@sentry/vite-plugin'

function vueFiles(directory) {
  if (! existsSync(directory)) {
    return []
  }

  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name)
    if (entry.isDirectory()) {
      return vueFiles(path)
    }

    return entry.name.endsWith('.vue') ? [path] : []
  })
}

function discoverPluginFrontends(root, environment) {
  const pluginsEnabled = environment.VITE_PLUGINS_ENABLED ?? environment.PLUGINS_ENABLED
  if (pluginsEnabled === 'false') {
    return []
  }

  const pluginsPath = join(root, 'plugins')
  if (! existsSync(pluginsPath)) {
    return []
  }

  return readdirSync(pluginsPath, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .flatMap((entry) => {
      const pluginDirectory = join(pluginsPath, entry.name)
      const manifestPath = join(pluginDirectory, 'plugin.json')
      if (! existsSync(manifestPath)) {
        return []
      }

      const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))
      const frontend = manifest.frontend
      if (! frontend || manifest.enabled === false) {
        return []
      }

      const featureGate = frontend.feature_gate
      if (featureGate && environment[`FEATURE_${featureGate.toUpperCase()}`] === 'false') {
        return []
      }

      const pagesPattern = String(frontend.pages ?? '')
      const pagesDirectory = resolve(pluginDirectory, pagesPattern.replace('/**/*.vue', ''))
      const pageFiles = vueFiles(pagesDirectory)

      return [{
        alias: `@plugins/${manifest.slug}`,
        root: resolve(pluginDirectory, 'resources/js'),
        pages: pageFiles.map((file) => ({
          key: `./Pages/${relative(pagesDirectory, file).replaceAll('\\', '/').replace(/\.vue$/, '.vue')}`,
          file,
        })),
      }]
    })
}

function gaeldPluginFrontendPages(frontends) {
  const virtualModuleId = 'virtual:gaeld-plugin-pages'
  const resolvedVirtualModuleId = `\0${virtualModuleId}`

  return {
    name: 'gaeld-plugin-frontend-pages',
    resolveId(id) {
      return id === virtualModuleId ? resolvedVirtualModuleId : undefined
    },
    load(id) {
      if (id !== resolvedVirtualModuleId) {
        return undefined
      }

      const entries = frontends.flatMap((frontend) => frontend.pages.map((page) => [page.key, page.file]))
      const serializedEntries = entries
        .map(([key, file]) => `${JSON.stringify(key)}: () => import(${JSON.stringify(file)})`)
        .join(',\n')

      return `export default {\n${serializedEntries}\n}`
    },
  }
}

export default defineConfig(({ mode }) => {
  const environment = { ...loadEnv(mode, process.cwd(), ''), ...process.env }
  const frontends = discoverPluginFrontends(__dirname, environment)
  const aliases = Object.fromEntries(frontends.map((frontend) => [frontend.alias, frontend.root]))

  return {
    plugins: [
      gaeldPluginFrontendPages(frontends),
      tailwindcss(),
      laravel({
        input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/cookieConsent.js'],
        refresh: true,
      }),
      vue({
        template: {
          transformAssetUrls: {
            base: null,
            includeAbsolute: false,
          },
        },
      }),
      sentryVitePlugin({
        org: process.env.SENTRY_ORG,
        project: process.env.SENTRY_PROJECT,
        authToken: process.env.SENTRY_AUTH_TOKEN,
        sourcemaps: {
          assets: ['./public/build/**'],
        },
        disable: !process.env.SENTRY_AUTH_TOKEN,
      }),
    ],
    build: {
      modulePreload: {
        polyfill: false,
      },
      sourcemap: true,
    },
    resolve: {
      alias: {
        '@': resolve(__dirname, 'resources/js'),
        ...aliases,
      },
    },
  }
})
