<script setup>
import { Lightbulb } from 'lucide-vue-next'
import { useHelp } from '@/lib/useHelp'

defineProps({
  title: {
    type: String,
    default: null,
  },
})

const { showHelp } = useHelp()
</script>

<template>
  <div
    v-if="showHelp"
    class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30"
  >
    <div class="flex gap-3">
      <Lightbulb class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
      <div class="text-sm text-amber-900 dark:text-amber-200">
        <p v-if="title" class="mb-1 font-medium">{{ title }}</p>
        <div class="leading-relaxed [&>p]:mt-1 first:[&>p]:mt-0">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>
