<script setup>
import { cn } from '@/lib/utils'

defineProps({
  class: String,
})
</script>

<template>
  <p :class="cn('text-sm text-[hsl(var(--muted-foreground))]', $props.class)">
    <slot />
  </p>
</template>
