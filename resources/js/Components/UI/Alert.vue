<script setup>
import { computed } from 'vue'
import { CheckCircle2, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  variant: {
    type: String,
    default: 'info',
    validator: (v) => ['success', 'error', 'warning', 'info'].includes(v),
  },
  title: { type: String, default: null },
  dismissable: { type: Boolean, default: false },
})

const emit = defineEmits(['dismiss'])

const config = computed(() => {
  switch (props.variant) {
    case 'success':
      return {
        wrapper: 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/40',
        icon: CheckCircle2,
        iconClass: 'text-green-600 dark:text-green-400',
        titleClass: 'text-green-900 dark:text-green-200',
        textClass: 'text-green-800 dark:text-green-300',
        dismissClass: 'text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-200',
      }
    case 'error':
      return {
        wrapper: 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/40',
        icon: XCircle,
        iconClass: 'text-red-600 dark:text-red-400',
        titleClass: 'text-red-900 dark:text-red-200',
        textClass: 'text-red-800 dark:text-red-300',
        dismissClass: 'text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200',
      }
    case 'warning':
      return {
        wrapper: 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40',
        icon: AlertTriangle,
        iconClass: 'text-amber-600 dark:text-amber-400',
        titleClass: 'text-amber-900 dark:text-amber-200',
        textClass: 'text-amber-800 dark:text-amber-300',
        dismissClass: 'text-amber-600 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-200',
      }
    default: // info
      return {
        wrapper: 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/40',
        icon: Info,
        iconClass: 'text-blue-600 dark:text-blue-400',
        titleClass: 'text-blue-900 dark:text-blue-200',
        textClass: 'text-blue-800 dark:text-blue-300',
        dismissClass: 'text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-200',
      }
  }
})
</script>

<template>
  <div :class="['rounded-lg border p-4', config.wrapper]" role="alert">
    <div class="flex gap-3">
      <component
        :is="config.icon"
        class="mt-0.5 h-4 w-4 shrink-0"
        :class="config.iconClass"
        aria-hidden="true"
      />
      <div class="flex-1 min-w-0">
        <p v-if="title" class="text-sm font-semibold" :class="config.titleClass">{{ title }}</p>
        <div class="text-sm" :class="[config.textClass, title ? 'mt-1' : '']">
          <slot />
        </div>
      </div>
      <button
        v-if="dismissable"
        type="button"
        class="shrink-0 rounded p-0.5 transition-colors"
        :class="config.dismissClass"
        :aria-label="t('dismiss')"
        @click="emit('dismiss')"
      >
        <X class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>
