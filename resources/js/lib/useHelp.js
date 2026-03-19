import { computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

export function useHelp() {
  const page = usePage()

  const showHelp = computed(() => page.props.auth?.user?.show_help ?? false)

  function toggleHelp() {
    router.post('/profile/toggle-help', {}, { preserveScroll: true })
  }

  return { showHelp, toggleHelp }
}
