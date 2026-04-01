<script setup>
import { cn } from '@/lib/utils'

defineProps({
  modelValue: [String, Number],
  label: String,
  id: String,
  error: String,
  required: Boolean,
  placeholder: String,
  class: String,
  rows: {
    type: [String, Number],
    default: 3,
  },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div :class="cn('space-y-2', $props.class)">
    <label
      v-if="label"
      :for="id"
      class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
    >
      {{ label }}
      <span v-if="required" class="text-[hsl(var(--destructive))]">*</span>
    </label>
    <textarea
      :id="id"
      :value="modelValue"
      :required="required"
      :placeholder="placeholder"
      :rows="rows"
      :aria-describedby="error ? id + '-error' : undefined"
      :aria-invalid="error ? true : undefined"
      :class="cn(
        'flex w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-[hsl(var(--muted-foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))] disabled:cursor-not-allowed disabled:opacity-50',
        error && 'border-[hsl(var(--destructive))]'
      )"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <p v-if="error" :id="id + '-error'" role="alert" class="text-xs text-[hsl(var(--destructive))]">{{ error }}</p>
  </div>
</template>
