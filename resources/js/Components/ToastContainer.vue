<script setup>
import { useToast } from '@/lib/useToast'
import { useTranslations } from '@/lib/useTranslations'

const { t: tl } = useTranslations()

const { toasts, dismiss } = useToast()

const variantClasses = {
  success: 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200',
  error: 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
  warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
  info: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200',
}
</script>

<template>
  <div class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none" aria-live="polite">
    <TransitionGroup
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="t in toasts"
        :key="t.id"
        :class="[
          'pointer-events-auto rounded-md border px-4 py-3 text-sm shadow-lg max-w-sm',
          variantClasses[t.variant] || variantClasses.info,
        ]"
        role="status"
      >
        <div class="flex items-start justify-between gap-2">
          <span>{{ t.message }}</span>
          <button @click="dismiss(t.id)" class="shrink-0 opacity-60 hover:opacity-100" :aria-label="tl('dismiss')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>
