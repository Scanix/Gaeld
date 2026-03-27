<script setup>
import { computed } from 'vue'
import { Info, Lightbulb, AlertTriangle } from 'lucide-vue-next'
import { useHelp } from '@/lib/useHelp'

const props = defineProps({
  title: {
    type: String,
    default: null,
  },
  level: {
    type: String,
    default: 'tip',
    validator: (v) => ['info', 'tip', 'warning', 'step'].includes(v),
  },
  learnMoreUrl: {
    type: String,
    default: null,
  },
  stepNumber: {
    type: Number,
    default: null,
  },
})

const { showHelp } = useHelp()

const config = computed(() => {
  switch (props.level) {
    case 'info':
      return {
        wrapper: 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30',
        icon: Info,
        iconClass: 'text-blue-600 dark:text-blue-400',
        textClass: 'text-blue-900 dark:text-blue-200',
      }
    case 'warning':
      return {
        wrapper: 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30',
        icon: AlertTriangle,
        iconClass: 'text-amber-600 dark:text-amber-400',
        textClass: 'text-amber-900 dark:text-amber-200',
      }
    case 'step':
      return {
        wrapper: 'border-violet-200 bg-violet-50 dark:border-violet-800 dark:bg-violet-950/30',
        icon: null,
        iconClass: 'text-violet-600 dark:text-violet-400',
        textClass: 'text-violet-900 dark:text-violet-200',
      }
    default: // tip
      return {
        wrapper: 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30',
        icon: Lightbulb,
        iconClass: 'text-amber-600 dark:text-amber-400',
        textClass: 'text-amber-900 dark:text-amber-200',
      }
  }
})
</script>

<template>
  <div
    v-if="showHelp"
    :class="['rounded-lg border p-4', config.wrapper]"
  >
    <div class="flex gap-3">
      <!-- Step number badge -->
      <div
        v-if="level === 'step'"
        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-violet-600 text-xs font-bold text-white dark:bg-violet-400 dark:text-violet-900"
      >
        {{ stepNumber ?? '' }}
      </div>
      <!-- Icon -->
      <component
        :is="config.icon"
        v-else
        class="mt-0.5 h-5 w-5 shrink-0"
        :class="config.iconClass"
      />

      <div :class="['text-sm flex-1', config.textClass]">
        <p v-if="title" class="mb-1 font-medium">{{ title }}</p>
        <div class="leading-relaxed [&>p]:mt-1 first:[&>p]:mt-0">
          <slot />
        </div>
        <a
          v-if="learnMoreUrl"
          :href="learnMoreUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-2 inline-block text-xs font-medium underline opacity-80 hover:opacity-100"
        >
          Learn more →
        </a>
      </div>
    </div>
  </div>
</template>

