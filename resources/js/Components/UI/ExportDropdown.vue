<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Download, ChevronDown, FileText, Sheet } from 'lucide-vue-next'
import Button from './Button.vue'

const props = defineProps({
  baseUrl: { type: String, required: true },
  params: { type: Object, default: () => ({}) },
})

const open = ref(false)
const dropdownRef = ref(null)

function buildUrl(format) {
  const filtered = Object.fromEntries(
    Object.entries(props.params).filter(([, v]) => v != null && v !== '')
  )
  const qs = new URLSearchParams(filtered).toString()
  return `${props.baseUrl}/${format}${qs ? '?' + qs : ''}`
}

function exportAs(format) {
  window.location.href = buildUrl(format)
  open.value = false
}

function handleClickOutside(e) {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<template>
  <div ref="dropdownRef" class="relative">
    <Button variant="outline" size="sm" @click="open = !open">
      <Download class="mr-1.5 h-4 w-4" />
      Export
      <ChevronDown class="ml-1.5 h-3 w-3" />
    </Button>

    <div
      v-if="open"
      class="absolute right-0 z-50 mt-1 w-44 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--background))] p-1 shadow-lg"
    >
      <button
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
        @click="exportAs('pdf')"
      >
        <FileText class="h-4 w-4 shrink-0" />
        Export PDF
      </button>
      <button
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
        @click="exportAs('csv')"
      >
        <Sheet class="h-4 w-4 shrink-0" />
        Export CSV
      </button>
    </div>
  </div>
</template>
