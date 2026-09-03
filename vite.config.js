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

function readEditionCompatibility(root) {
  try {
    const contract = JSON.parse(readFileSync(join(root, 'contract/edition-boundary.json'), 'utf8'))
    return contract?.compatibility ?? null
  } catch {
    return null
  }
}

function versionParts(version) {
  if (typeof version !== 'string' || !/^\d+\.\d+\.\d+$/.test(version)) {
    return null
  }

  return version.split('.').map(Number)
}

function compareVersions(left, right) {
  for (let index = 0; index < left.length; index += 1) {
    if (left[index] !== right[index]) {
      return left[index] - right[index]
    }
  }

  return 0
}

function hasCompatibleEditionMetadata(root, manifest) {
  const compatibility = manifest.compatibility
  if (manifest.slug !== 'gaeld-ee' && compatibility === undefined) {
    return true
  }

  const contract = readEditionCompatibility(root)
  const versionRange = contract?.supported_ee_range
  const range = typeof versionRange === 'string'
    ? versionRange.match(/^>=(\d+\.\d+\.\d+) <(\d+\.\d+\.\d+)$/)
    : null
  const pluginVersion = versionParts(compatibility?.ee_version)
  const minimumVersion = range ? versionParts(range[1]) : null
  const maximumVersion = range ? versionParts(range[2]) : null

  return Boolean(
    contract &&
    typeof contract.contract_version === 'string' &&
    compatibility &&
    typeof compatibility === 'object' &&
    !Array.isArray(compatibility) &&
    compatibility.contract_version === contract.contract_version &&
    pluginVersion &&
    minimumVersion &&
    maximumVersion &&
    compareVersions(pluginVersion, minimumVersion) >= 0 &&
    compareVersions(pluginVersion, maximumVersion) < 0
  )
}

function discoverPluginFrontends(root, environment) {
  const pluginsEnabled = environment.VITE_PLUGINS_ENABLED ?? environment.PLUGINS_ENABLED
  if (pluginsEnabled !== 'true') {
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

      let manifest
      try {
        manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))
      } catch {
        return []
      }

      if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) {
        return []
      }

      if (
        typeof manifest.slug !== 'string' ||
        !/^[a-z0-9][a-z0-9-]{0,63}$/.test(manifest.slug) ||
        typeof manifest.provider !== 'string' ||
        !manifest.provider.startsWith('Plugins\\')
      ) {
        return []
      }

      if (!hasCompatibleEditionMetadata(root, manifest)) {
        return []
      }

      const frontend = manifest.frontend
      if (!frontend || typeof frontend !== 'object' || Array.isArray(frontend) || manifest.enabled === false) {
        return []
      }

      const featureGate = frontend.feature_gate
      if (featureGate && environment[`FEATURE_${featureGate.toUpperCase()}`] !== 'true') {
        return []
      }

      const pagesPattern = String(frontend.pages ?? '')
      if (!pagesPattern.endsWith('/**/*.vue')) {
        return []
      }

      const pagesDirectory = resolve(pluginDirectory, pagesPattern.replace('/**/*.vue', ''))
      const pluginRoot = resolve(pluginDirectory)
      if (pagesDirectory !== pluginRoot && !pagesDirectory.startsWith(`${pluginRoot}/`)) {
        return []
      }

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
  const frontends = discoverPluginFrontends(import.meta.dirname, environment)
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
        '@': resolve(import.meta.dirname, 'resources/js'),
        ...aliases,
      },
    },
  }
})
