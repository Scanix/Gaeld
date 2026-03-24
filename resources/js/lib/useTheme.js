import { ref, computed, watchEffect } from 'vue'

const STORAGE_KEY = 'gaeld-theme'

const theme = ref(typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) ?? 'system' : 'system')

function getSystemDark() {
  return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches
}

const isDark = computed(() => {
  if (theme.value === 'dark') return true
  if (theme.value === 'light') return false
  return getSystemDark()
})

function applyTheme() {
  const dark = isDark.value
  document.documentElement.classList.toggle('dark', dark)
  document.querySelector('meta[name="theme-color"]')
    ?.setAttribute('content', dark ? '#0a0a0a' : '#33cc66')
}

let mediaListenerAttached = false

export function useTheme() {
  if (typeof window !== 'undefined' && !mediaListenerAttached) {
    mediaListenerAttached = true
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      if (theme.value === 'system') applyTheme()
    })
  }

  watchEffect(applyTheme)

  function setTheme(value) {
    theme.value = value
    localStorage.setItem(STORAGE_KEY, value)
  }

  function toggleTheme() {
    const next = isDark.value ? 'light' : 'dark'
    setTheme(next)
  }

  return { theme, isDark, setTheme, toggleTheme }
}
