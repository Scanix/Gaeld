import { ref } from 'vue'

const toasts = ref([])
let nextId = 0

export function useToast() {
  function toast(message, variant = 'success', duration = 4000) {
    const id = ++nextId
    toasts.value.push({ id, message, variant, visible: true })
    if (duration > 0) {
      setTimeout(() => dismiss(id), duration)
    }
  }

  function dismiss(id) {
    const t = toasts.value.find((t) => t.id === id)
    if (t) t.visible = false
    setTimeout(() => {
      toasts.value = toasts.value.filter((t) => t.id !== id)
    }, 300)
  }

  return { toasts, toast, dismiss }
}
