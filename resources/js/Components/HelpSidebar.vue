<script setup>
import { computed, ref, onMounted } from 'vue'
import { X } from 'lucide-vue-next'
import Button from './UI/Button.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  page: {
    type: String,
    required: true,
  },
  baseUrl: {
    type: String,
    default: 'http://localhost:3000',
  },
})

defineEmits(['close'])

const iframeSrc = computed(() => `${props.baseUrl}/docs/${props.page}`)
const loadError = ref(false)

function onIframeError() {
  loadError.value = true
}

onMounted(() => {
  // Check if iframe can load by testing a fetch (same-origin only)
  // Falls back gracefully if docs server is unavailable
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), 3000)
  fetch(iframeSrc.value, { mode: 'no-cors', signal: controller.signal })
    .catch(() => { loadError.value = true })
    .finally(() => clearTimeout(timeout))
})
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
