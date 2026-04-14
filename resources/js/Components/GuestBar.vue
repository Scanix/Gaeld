<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Sun, Moon } from 'lucide-vue-next'
import { useTheme } from '@/lib/useTheme'
import { useTranslations } from '@/lib/useTranslations'

const { isDark, toggleTheme } = useTheme()
const { locale } = useTranslations()
const page = usePage()

const LOCALES = [
  { value: 'en', label: 'EN' },
  { value: 'fr', label: 'FR' },
  { value: 'de', label: 'DE' },
  { value: 'it', label: 'IT' },
]

function switchLocale(lang) {
  if (lang === locale.value) return
  router.get(page.url.split('?')[0], { lang }, { preserveScroll: true, replace: true })
}
</script>

<template>
  <div class="fixed top-3 right-4 z-50 flex items-center gap-1">
    <button
      v-for="l in LOCALES"
      :key="l.value"
      :aria-label="l.label"
      :aria-pressed="locale === l.value"
      :class="[
        'h-8 rounded px-2 text-xs font-medium transition-colors',
        locale === l.value
          ? 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]'
          : 'text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]',
      ]"
      @click="switchLocale(l.value)"
    >
      {{ l.label }}
    </button>

    <div class="ml-1 h-5 w-px bg-[hsl(var(--border))]" aria-hidden="true" />

    <button
      class="flex h-8 w-8 items-center justify-center rounded text-[hsl(var(--muted-foreground))] transition-colors hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
      :title="isDark ? 'Light mode' : 'Dark mode'"
      @click="toggleTheme"
    >
      <Sun v-if="isDark" class="h-4 w-4" />
      <Moon v-else class="h-4 w-4" />
    </button>
  </div>
</template>
