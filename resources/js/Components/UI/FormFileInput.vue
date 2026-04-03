<script setup>
import { cn } from '@/lib/utils'

defineProps({
  id: { type: String, required: true },
  label: String,
  accept: { type: String, default: '.pdf,.jpg,.jpeg,.png' },
  error: String,
  class: String,
})

defineEmits(['change'])
</script>

<template>
  <div :class="cn('space-y-2', $props.class)">
    <label v-if="label" :for="id" class="block text-sm font-medium leading-none">
      {{ label }}
    </label>
    <input
      :id="id"
      type="file"
      :accept="accept"
      class="block w-full text-sm text-[hsl(var(--muted-foreground))] file:mr-4 file:rounded-md file:border-0 file:bg-[hsl(var(--primary))] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[hsl(var(--primary-foreground))] hover:file:opacity-90"
      @change="$emit('change', $event)"
    />
    <p v-if="error" role="alert" class="text-xs text-[hsl(var(--destructive))]">{{ error }}</p>
    <slot />
  </div>
</template>
