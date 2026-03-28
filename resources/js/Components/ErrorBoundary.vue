<script setup>
import { ref, onErrorCaptured } from 'vue'
import { useTranslations } from '@/lib/useTranslations'

const hasError = ref(false)
const errorMessage = ref('')
const { t } = useTranslations()

onErrorCaptured((err) => {
  hasError.value = true
  errorMessage.value = err?.message || t('unexpected_error_occurred')
  console.error('[ErrorBoundary]', err)
  return false // stop propagation
})

function retry() {
  hasError.value = false
  errorMessage.value = ''
}
</script>

<template>
  <div v-if="hasError" class="flex min-h-[300px] items-center justify-center px-4" role="alert">
    <div class="max-w-md text-center">
      <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[hsl(var(--destructive)/0.1)]">
        <svg class="h-6 w-6 text-[hsl(var(--destructive))]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
      </div>
      <h2 class="mb-2 text-lg font-semibold text-[hsl(var(--foreground))]">{{ t('something_went_wrong') }}</h2>
      <p class="mb-4 text-sm text-[hsl(var(--muted-foreground))]">{{ errorMessage }}</p>
      <button
        class="inline-flex items-center justify-center rounded-md bg-[hsl(var(--primary))] px-4 py-2 text-sm font-medium text-[hsl(var(--primary-foreground))] shadow hover:bg-[hsl(var(--primary)/0.9)] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
        @click="retry"
      >
        {{ t('try_again') }}
      </button>
    </div>
  </div>
  <slot v-else />
</template>
