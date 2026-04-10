<script setup>
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
})
</script>

<template>
  <div class="mb-6 flex items-center justify-between">
    <slot name="start">
      <div>
        <h2 v-if="title" class="text-xl font-semibold">{{ title }}</h2>
        <p v-if="description" class="mt-0.5 text-sm text-[hsl(var(--muted-foreground))]">{{ description }}</p>
      </div>
    </slot>
    <slot />
  </div>
</template>
