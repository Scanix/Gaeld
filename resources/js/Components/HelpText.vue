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
    class="rounded-lg border border-[hsl(var(--accent-foreground)/0.15)] bg-[hsl(var(--accent))] p-4"
  >
    <div class="flex gap-3">
      <Lightbulb class="mt-0.5 h-4 w-4 shrink-0 text-[hsl(var(--accent-foreground))]" />
      <div class="text-sm text-[hsl(var(--accent-foreground))]">
        <p v-if="title" class="mb-1 font-medium">{{ title }}</p>
        <div class="leading-relaxed opacity-80 [&>p]:mt-1 first:[&>p]:mt-0">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>
