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
    class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/30"
  >
    <div class="flex gap-3">
      <Lightbulb class="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
      <div class="text-sm text-blue-800 dark:text-blue-200">
        <p v-if="title" class="mb-1 font-medium">{{ title }}</p>
        <div class="leading-relaxed [&>p]:mt-1 first:[&>p]:mt-0">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>
