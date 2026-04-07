<script setup>
import { computed } from 'vue'
import { detectIbanType } from '@/lib/ibanUtils'
import { useTranslations } from '@/lib/useTranslations'
import { CircleCheck, TriangleAlert, Info } from 'lucide-vue-next'

const props = defineProps({
  iban: String,
  /** 'qr' = expects QR-IBAN only; 'any' = accepts both but shows which type */
  mode: {
    type: String,
    default: 'qr',
    validator: v => ['qr', 'any'].includes(v),
  },
})

const { t } = useTranslations()

const ibanType = computed(() => detectIbanType(props.iban))

const hint = computed(() => {
  switch (ibanType.value) {
    case 'empty':
      return { icon: Info, text: t('qr_iban_help_where_to_find'), color: 'text-[hsl(var(--muted-foreground))]' }
    case 'incomplete':
      return null
    case 'qr-iban':
      return { icon: CircleCheck, text: t('qr_iban_detected'), color: 'text-emerald-600 dark:text-emerald-400' }
    case 'regular-iban':
      if (props.mode === 'qr') {
        return { icon: TriangleAlert, text: t('qr_iban_regular_warning'), color: 'text-amber-600 dark:text-amber-400' }
      }
      return { icon: Info, text: t('iban_regular_detected'), color: 'text-[hsl(var(--muted-foreground))]' }
    case 'foreign-iban':
      if (props.mode === 'qr') {
        return { icon: TriangleAlert, text: t('qr_iban_swiss_only'), color: 'text-amber-600 dark:text-amber-400' }
      }
      return null
    default:
      return null
  }
})
</script>

<template>
  <p v-if="hint" class="mt-1 flex items-center gap-1 text-xs" :class="hint.color">
    <component :is="hint.icon" class="h-3.5 w-3.5 shrink-0" />
    <span>{{ hint.text }}</span>
  </p>
</template>
