<script setup>
import { cn } from '@/lib/utils'

defineProps({
  class: String,
})
</script>

<template>
  <div
    :class="cn(
      'animate-pulse rounded-md bg-[hsl(var(--muted))]',
      $props.class,
    )"
  />
</template>
