<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * hCaptcha widget wrapper.
 *
 * Lazy-loads the hCaptcha script on mount, renders the widget, and
 * emits the verification token via v-model. When no site key is
 * configured (Inertia shared props expose it via `props.site_key`),
 * the component renders nothing — the server-side rule will pass
 * through in that case too.
 */
const props = defineProps({
  modelValue: { type: String, default: '' },
  siteKey: { type: String, default: '' },
  theme: { type: String, default: 'light' },
})

const emit = defineEmits(['update:modelValue'])

const container = ref(null)
const widgetId = ref(null)

const enabled = computed(() => Boolean(props.siteKey))

const SCRIPT_SRC = 'https://hcaptcha.com/1/api.js?render=explicit'

function loadScript() {
  return new Promise((resolve, reject) => {
    if (typeof window === 'undefined') {
      reject(new Error('window unavailable'))
      return
    }

    if (window.hcaptcha) {
      resolve(window.hcaptcha)
      return
    }

    let existing = document.querySelector(`script[src="${SCRIPT_SRC}"]`)
    if (!existing) {
      existing = document.createElement('script')
      existing.src = SCRIPT_SRC
      existing.async = true
      existing.defer = true
      document.head.appendChild(existing)
    }

    const start = Date.now()
    const interval = setInterval(() => {
      if (window.hcaptcha) {
        clearInterval(interval)
        resolve(window.hcaptcha)
      } else if (Date.now() - start > 10000) {
        clearInterval(interval)
        reject(new Error('hcaptcha script timeout'))
      }
    }, 100)
  })
}

async function render() {
  if (!enabled.value || !container.value) return

  try {
    const hcaptcha = await loadScript()
    widgetId.value = hcaptcha.render(container.value, {
      sitekey: props.siteKey,
      theme: props.theme,
      callback: token => emit('update:modelValue', token),
      'expired-callback': () => emit('update:modelValue', ''),
      'error-callback': () => emit('update:modelValue', ''),
    })
  } catch (e) {
    // Silent — the form will still submit, server-side validation will reject.
  }
}

function reset() {
  if (window.hcaptcha && widgetId.value !== null) {
    window.hcaptcha.reset(widgetId.value)
  }
  emit('update:modelValue', '')
}

defineExpose({ reset })

onMounted(render)

onBeforeUnmount(() => {
  if (window.hcaptcha && widgetId.value !== null) {
    try {
      window.hcaptcha.remove(widgetId.value)
    } catch (e) {
      // ignore
    }
  }
})

// Reset widget when parent clears the token (e.g. after a failed submit).
watch(() => props.modelValue, value => {
  if (!value && widgetId.value !== null && window.hcaptcha) {
    try {
      window.hcaptcha.reset(widgetId.value)
    } catch (e) {
      // ignore
    }
  }
})
</script>

<template>
  <div v-if="enabled" ref="container" class="h-captcha" />
</template>
