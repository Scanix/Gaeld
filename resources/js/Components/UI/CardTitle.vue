<script setup>
import { cn } from '@/lib/utils'

defineProps({
  class: String,
})
</script>

<template>
  <h3 :class="cn('font-semibold leading-none tracking-tight', $props.class)">
    <slot />
  </h3>
</template>
