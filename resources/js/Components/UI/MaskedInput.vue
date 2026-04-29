<script>
const MASKS = {
  iban: { mask: 'AA## #### #### #### #### #', eager: true },
  phone: { mask: '+## ## ### ## ##' },
  postal: { mask: '####' },
}
</script>

<script setup>
import { vMaska } from 'maska/vue'
import { cn } from '@/lib/utils'

const props = defineProps({
  modelValue: [String, Number],
  label: String,
  id: String,
  mask: {
    type: String,
    validator: (v) => ['iban', 'phone', 'postal'].includes(v),
  },
  error: String,
  required: Boolean,
  placeholder: String,
  class: String,
})

const emit = defineEmits(['update:modelValue'])

const maskaOptions = MASKS[props.mask]
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
    <input
      v-maska:[maskaOptions]
      :id="id"
      type="text"
      :value="modelValue"
      :required="required"
      :placeholder="placeholder"
      :aria-describedby="error ? id + '-error' : undefined"
      :aria-invalid="error ? true : undefined"
      :class="cn(
        'flex h-10 w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-[hsl(var(--muted-foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))] disabled:cursor-not-allowed disabled:opacity-50 sm:h-9',
        error && 'border-[hsl(var(--destructive))]'
      )"
      @maska="$emit('update:modelValue', $event.detail.unmasked)"
    >
    <p v-if="error" :id="id + '-error'" role="alert" class="text-xs text-[hsl(var(--destructive))]">{{ error }}</p>
  </div>
</template>
