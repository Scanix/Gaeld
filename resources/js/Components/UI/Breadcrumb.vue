<script setup>
import { ChevronRight } from 'lucide-vue-next'
import { Link } from '@inertiajs/vue3'

defineProps({
  items: {
    type: Array,
    required: true,
    // Each item: { label: string, href?: string }
    // Last item is current page (no link)
  },
})
</script>

<template>
  <nav aria-label="Breadcrumb" class="mb-4">
    <ol class="flex items-center gap-1 text-sm text-[hsl(var(--muted-foreground))]">
      <li v-for="(item, index) in items" :key="index" class="flex items-center gap-1">
        <ChevronRight v-if="index > 0" class="h-3.5 w-3.5 shrink-0" />
        <Link
          v-if="item.href && index < items.length - 1"
          :href="item.href"
          class="hover:text-[hsl(var(--foreground))] transition-colors"
        >
          {{ item.label }}
        </Link>
        <span
          v-else
          class="font-medium text-[hsl(var(--foreground))]"
          :aria-current="index === items.length - 1 ? 'page' : undefined"
        >
          {{ item.label }}
        </span>
      </li>
    </ol>
  </nav>
</template>
