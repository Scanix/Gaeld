<script setup>
import { cn } from '@/lib/utils'

defineProps({
  modelValue: [String, Number],
  label: String,
  id: String,
  options: {
    type: Array,
    required: true,
    // Each option: { value: string, label: string } or plain string
  },
  error: String,
  required: Boolean,
  placeholder: String,
  class: String,
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div :class="cn('space-y-2', $props.class)">
    <label
      v-if="label"
      :for="id"
      class="text-sm font-medium leading-none"
    >
      {{ label }}
      <span v-if="required" class="text-[hsl(var(--destructive))]">*</span>
    </label>
    <select
      :id="id"
      :value="modelValue"
      :required="required"
      :class="cn(
        'flex h-9 w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))] disabled:cursor-not-allowed disabled:opacity-50',
        error && 'border-[hsl(var(--destructive))]'
      )"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option
        v-for="opt in options"
        :key="typeof opt === 'string' ? opt : opt.value"
        :value="typeof opt === 'string' ? opt : opt.value"
      >
        {{ typeof opt === 'string' ? opt : opt.label }}
      </option>
    </select>
    <p v-if="error" class="text-xs text-[hsl(var(--destructive))]">{{ error }}</p>
  </div>
</template>
