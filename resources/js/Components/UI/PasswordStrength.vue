<script setup>
import { computed } from 'vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  password: {
    type: String,
    default: '',
  },
})

const strength = computed(() => {
  const p = props.password
  if (!p) return 0

  let score = 0
  if (p.length >= 8) score++
  if (p.length >= 12) score++
  if (/[a-z]/.test(p) && /[A-Z]/.test(p)) score++
  if (/\d/.test(p)) score++
  if (/[^a-zA-Z0-9]/.test(p)) score++
  return Math.min(score, 4)
})

const label = computed(() => {
  const labels = ['', t('password_weak'), t('password_fair'), t('password_good'), t('password_strong')]
  return labels[strength.value]
})

const color = computed(() => {
  const colors = [
    'bg-[hsl(var(--muted))]',
    'bg-red-500',
    'bg-orange-500',
    'bg-yellow-500',
    'bg-green-500',
  ]
  return colors[strength.value]
})
</script>

<template>
  <div v-if="password" class="space-y-1.5">
    <div class="flex gap-1">
      <div
        v-for="i in 4"
        :key="i"
        class="h-1 flex-1 rounded-full transition-colors duration-200"
        :class="i <= strength ? color : 'bg-[hsl(var(--muted))]'"
      />
    </div>
    <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ label }}</p>
  </div>
</template>
