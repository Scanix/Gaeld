<script setup>
defineProps({
  title: { type: String, default: '' },
  description: { type: String, default: '' },
})
</script>

<template>
  <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <slot name="start">
      <div class="min-w-0">
        <h2 v-if="title" class="text-xl font-semibold truncate">{{ title }}</h2>
        <p v-if="description" class="mt-0.5 text-sm text-[hsl(var(--muted-foreground))]">{{ description }}</p>
      </div>
    </slot>
    <div class="shrink-0">
      <slot />
    </div>
  </div>
</template>
