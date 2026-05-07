<script setup>
import { computed, ref } from 'vue'
import { X } from 'lucide-vue-next'
import Button from './UI/Button.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  page: {
    type: String,
    default: null,
  },
  baseUrl: {
    type: String,
    default: null,
  },
  locale: {
    type: String,
    default: 'en',
  },
})

defineEmits(['close'])

const HELP_PAGE_ALIASES = {
  assets: 'fixed-assets',
  accounting: 'accounting-basics',
  settings: 'user-management',
  'vat-rates': 'vat',
}

const normalizedPage = computed(() => {
  if (!props.page) return null
  return HELP_PAGE_ALIASES[props.page] ?? props.page
})

// Docusaurus i18n: English has no prefix, other languages use /<locale>/docs/<page>
const localizedPath = computed(() => {
  const prefix = props.locale && props.locale !== 'en'
    ? `/${props.locale}`
    : ''
  return normalizedPage.value ? `${prefix}/docs/${normalizedPage.value}` : `${prefix}/docs`
})
const iframeSrc = computed(() => props.baseUrl ? `${props.baseUrl}${localizedPath.value}` : null)
const loadError = ref(!props.baseUrl)

function onIframeError() {
  loadError.value = true
}
</script>

<template>
  <div class="fixed right-0 top-0 z-50 h-screen w-96 border-l border-[hsl(var(--border))] bg-[hsl(var(--background))] shadow-xl">
    <div class="flex items-center justify-between border-b border-[hsl(var(--border))] px-4 py-3">
      <span class="text-sm font-semibold">{{ t('documentation') }}</span>
      <Button variant="ghost" size="icon" @click="$emit('close')">
        <X class="h-4 w-4" />
      </Button>
    </div>
    <iframe
      v-if="!loadError"
      :src="iframeSrc"
      class="h-[calc(100vh-49px)] w-full border-0"
      sandbox="allow-scripts allow-same-origin"
      @error="onIframeError"
    />
    <div v-else class="flex h-[calc(100vh-49px)] items-center justify-center p-6">
      <div class="text-center">
        <p class="mb-2 text-sm font-medium text-[hsl(var(--muted-foreground))]">{{ t('doc_unavailable') }}</p>
        <p class="text-xs text-[hsl(var(--muted-foreground))]">
          {{ t('doc_not_running') }}
        </p>
      </div>
    </div>
  </div>
</template>
