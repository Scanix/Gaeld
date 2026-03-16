<script setup>
import { computed } from 'vue'
import { X } from 'lucide-vue-next'
import Button from './UI/Button.vue'

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
</script>

<template>
  <div class="fixed right-0 top-0 z-50 h-screen w-96 border-l border-[hsl(var(--border))] bg-[hsl(var(--background))] shadow-xl">
    <div class="flex items-center justify-between border-b border-[hsl(var(--border))] px-4 py-3">
      <span class="text-sm font-semibold">Documentation</span>
      <Button variant="ghost" size="icon" @click="$emit('close')">
        <X class="h-4 w-4" />
      </Button>
    </div>
    <iframe
      :src="iframeSrc"
      class="h-[calc(100vh-49px)] w-full border-0"
      sandbox="allow-scripts allow-same-origin"
    />
  </div>
</template>
