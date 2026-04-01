<script setup>
import Button from '@/Components/UI/Button.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  icon: { type: [Object, Function], default: null },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  actionLabel: { type: String, default: '' },
  actionHref: { type: String, default: '' },
})

defineEmits(['action'])
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12 text-center">
    <component :is="icon" v-if="icon" class="mb-4 h-12 w-12 text-[hsl(var(--muted-foreground))]" />
    <h3 class="mb-1 text-sm font-medium text-[hsl(var(--foreground))]">{{ title }}</h3>
    <p v-if="description" class="mb-4 max-w-sm text-sm text-[hsl(var(--muted-foreground))]">{{ description }}</p>
    <slot name="actions">
      <Button v-if="actionLabel && actionHref" as="a" :href="actionHref" size="sm">
        {{ actionLabel }}
      </Button>
      <Button v-else-if="actionLabel" size="sm" @click="$emit('action')">
        {{ actionLabel }}
      </Button>
    </slot>
  </div>
</template>
