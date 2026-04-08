import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Warns users before navigating away from a page with unsaved form changes.
 * Handles both browser navigation (beforeunload) and Inertia SPA navigation.
 *
 * When `onSave` is provided, navigations are intercepted with a reactive dialog
 * state (`showDialog`) instead of the native `confirm()`. The dialog offers
 * three choices: save as draft, discard, or stay.
 *
 * @param {import('vue').Ref<boolean>|import('vue').ComputedRef<boolean>} isDirty
 * @param {object} [options]
 * @param {string}   [options.message]  - Message for browsers that still show it
 * @param {Function} [options.onSave]   - Save-as-draft callback. Return a promise.
 * @param {string}   [options.fallbackUrl] - URL to navigate to after discard (default: history.back)
 * @returns {{ showDialog: import('vue').Ref<boolean>, handleSave: Function, handleDiscard: Function, handleStay: Function }}
 */
export function useUnsavedChanges(isDirty, options = {}) {
  const message = options.message || 'You have unsaved changes. Are you sure you want to leave?'
  const onSave = options.onSave || null
  const fallbackUrl = options.fallbackUrl || null

  const showDialog = ref(false)
  const forceClear = ref(false)
  let pendingVisit = null

  function onBeforeUnload(e) {
    if (forceClear.value || !isDirty.value) return
    e.preventDefault()
    e.returnValue = message
  }

  let removeInertiaListener = null

  onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload)

    removeInertiaListener = router.on('before', (event) => {
      if (forceClear.value || !isDirty.value) return true

      if (onSave) {
        // Custom dialog mode: intercept and show our dialog
        pendingVisit = event.detail.visit
        showDialog.value = true
        event.preventDefault()
        return false
      }

      // Fallback: native confirm
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

  function continueNavigation() {
    showDialog.value = false
    if (pendingVisit) {
      const visit = pendingVisit
      pendingVisit = null
      router.visit(visit.url, { method: visit.method, ...visit })
    } else if (fallbackUrl) {
      router.visit(fallbackUrl)
    } else {
      window.history.back()
    }
  }

  function handleSave() {
    if (!onSave) return
    const result = onSave()
    if (result && typeof result.then === 'function') {
      result.then(() => { showDialog.value = false })
    } else {
      showDialog.value = false
    }
  }

  function handleDiscard() {
    // Use forceClear to bypass dirty check (isDirty may be a read-only computed)
    forceClear.value = true
    continueNavigation()
  }

  function handleStay() {
    pendingVisit = null
    showDialog.value = false
  }

  return { showDialog, handleSave, handleDiscard, handleStay, forceClear }
}
