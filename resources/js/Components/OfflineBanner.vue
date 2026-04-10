<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const isOnline = ref(true)

function updateOnlineStatus() {
  isOnline.value = navigator.onLine
}

onMounted(() => {
  isOnline.value = navigator.onLine
  window.addEventListener('online', updateOnlineStatus)
  window.addEventListener('offline', updateOnlineStatus)
})

onBeforeUnmount(() => {
  window.removeEventListener('online', updateOnlineStatus)
  window.removeEventListener('offline', updateOnlineStatus)
})
</script>

<template>
  <Transition name="offline-banner">
    <div
      v-if="!isOnline"
      class="no-print relative z-40 bg-[hsl(var(--warning))] text-[hsl(var(--warning-foreground))] text-sm font-medium"
      role="status"
      aria-live="polite"
    >
      <div class="flex items-center justify-center gap-2 px-6 py-2">
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M12 12h.01M6.343 17.657A9 9 0 015.636 5.636M8.464 15.536a5 5 0 01-.707-7.072"
          />
        </svg>
        <span>{{ t('offline_banner') }}</span>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.offline-banner-enter-active,
.offline-banner-leave-active {
  overflow: hidden;
  max-height: 40px;
  transition: max-height 0.2s ease, opacity 0.2s ease;
}
.offline-banner-enter-from,
.offline-banner-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>
