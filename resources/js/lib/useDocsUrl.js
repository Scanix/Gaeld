import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/**
 * Build absolute URLs to the external docs site (Docusaurus project).
 *
 * Both `docsBaseUrl` and `docsRoutes` are shared via HandleInertiaRequests.
 *
 * Usage:
 *   const { url } = useDocsUrl()
 *   <SearchableSelect :help-href="url('chart-of-accounts')" ... />
 */
export function useDocsUrl() {
  const page = usePage()

  const baseUrl = computed(() => (page.props?.docsBaseUrl ?? '').replace(/\/$/, ''))
  const routes = computed(() => page.props?.docsRoutes ?? {})

  function url(routeKey) {
    const path = routes.value?.[routeKey]
    if (!path) return baseUrl.value || ''
    return baseUrl.value + path
  }

  return { url, baseUrl, routes }
}
