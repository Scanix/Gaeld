<script setup>
import { cn } from '@/lib/utils'
import { computed, ref } from 'vue'

const props = defineProps({
  modelValue: [String, Number],
  label: String,
  id: String,
  type: {
    type: String,
    default: 'text',
  },
  error: String,
  hint: String,
  required: Boolean,
  placeholder: String,
  autocomplete: String,
  class: String,
})

defineEmits(['update:modelValue'])

const passwordVisible = ref(false)

const isPassword = computed(() => props.type === 'password')

const effectiveType = computed(() => {
  if (!isPassword.value) {
    return props.type
  }

  return passwordVisible.value ? 'text' : 'password'
})

function togglePasswordVisibility() {
  passwordVisible.value = !passwordVisible.value
}
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
    <div class="relative">
      <input
        :id="id"
        :type="effectiveType"
        :value="modelValue"
        :required="required"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :aria-describedby="error ? id + '-error' : undefined"
        :aria-invalid="error ? true : undefined"
        :class="cn(
          'flex h-11 w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-1 text-base shadow-sm transition-colors placeholder:text-[hsl(var(--muted-foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))] disabled:cursor-not-allowed disabled:opacity-50 sm:h-9 sm:text-sm',
          isPassword && 'pr-10',
          error && 'border-[hsl(var(--destructive))]'
        )"
        @input="$emit('update:modelValue', $event.target.value)"
      >
      <button
        v-if="isPassword"
        type="button"
        tabindex="-1"
        :aria-label="passwordVisible ? 'Hide password' : 'Show password'"
        :aria-pressed="passwordVisible"
        class="absolute inset-y-0 right-0 flex items-center px-3 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))] rounded-r-md"
        @click="togglePasswordVisibility"
      >
        <svg v-if="passwordVisible" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
          <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
          <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
          <line x1="2" y1="2" x2="22" y2="22" />
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
          <circle cx="12" cy="12" r="3" />
        </svg>
      </button>
    </div>
    <p v-if="error" :id="id + '-error'" role="alert" class="text-xs text-[hsl(var(--destructive))]">{{ error }}</p>
    <p v-else-if="hint" class="text-xs text-[hsl(var(--muted-foreground))]">{{ hint }}</p>
  </div>
</template>
