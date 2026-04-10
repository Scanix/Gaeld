<script setup>
import { X } from 'lucide-vue-next'
import { useTranslations } from '@/lib/useTranslations'

defineProps({
  color: { type: String, default: 'primary' }, // red | amber | primary | destructive | blue
  dismissable: { type: Boolean, default: false },
  role: { type: String, default: 'alert' },
})

defineEmits(['dismiss'])

const { t } = useTranslations()

const colorMap = {
  red: 'bg-red-600 text-white',
  amber: 'bg-amber-400 text-amber-950',
  primary: 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]',
  destructive: 'bg-[hsl(var(--destructive))] text-[hsl(var(--destructive-foreground))]',
  blue: 'bg-blue-600 text-white',
}
</script>

<template>
  <div :class="['relative z-40 text-sm font-medium', colorMap[color]]" :role="role">
    <div class="flex max-w-full items-center justify-center gap-3 px-6 py-2">
      <slot />
      <button
        v-if="dismissable"
        class="ml-2 opacity-60 transition-opacity hover:opacity-100"
        :aria-label="t('dismiss')"
        @click="$emit('dismiss')"
      >
        <X class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>
