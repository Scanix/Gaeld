import { onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Warns users before navigating away from a page with unsaved form changes.
 * Handles both browser navigation (beforeunload) and Inertia SPA navigation.
 *
 * @param {import('vue').Ref<boolean>|import('vue').ComputedRef<boolean>} isDirty - Reactive ref indicating unsaved changes (e.g. form.isDirty)
 * @param {string} [message] - Optional message (only shown by some browsers)
 */
export function useUnsavedChanges(isDirty, message = 'You have unsaved changes. Are you sure you want to leave?') {
  function onBeforeUnload(e) {
    if (!isDirty.value) return
    e.preventDefault()
    e.returnValue = message
  }

  let removeInertiaListener = null

  onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload)

    removeInertiaListener = router.on('before', (event) => {
      if (!isDirty.value) return true

      if (!confirm(message)) {
        event.preventDefault()
        return false
      }
      return true
    })
  })

  onUnmounted(() => {
    window.removeEventListener('beforeunload', onBeforeUnload)
    if (removeInertiaListener) {
      removeInertiaListener()
    }
  })
}
