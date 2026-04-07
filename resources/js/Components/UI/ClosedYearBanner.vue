<script setup>
import { useTranslations } from '@/lib/useTranslations'
import { Lock } from 'lucide-vue-next'

defineProps({
  year: { type: Number, required: true },
})

const { t } = useTranslations()
</script>

<template>
  <div class="mb-6 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
    <Lock class="h-4 w-4 shrink-0" />
    <span>{{ t('fiscal_year_closed_banner', { year }) }}</span>
  </div>
</template>
