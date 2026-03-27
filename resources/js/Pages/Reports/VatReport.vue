<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import { useTranslations } from '@/lib/useTranslations'

const props = defineProps({ report: Object })
const { t } = useTranslations()
</script>

<template>
  <AppLayout :title="t('vat_report')">
    <!-- VAT report UI -->
  </AppLayout>
</template>
