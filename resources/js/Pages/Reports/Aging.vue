<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import { useTranslations } from '@/lib/useTranslations'

const props = defineProps({ report: Object, asOf: String })
const { t } = useTranslations()
</script>

<template>
  <AppLayout :title="t('aging_report')">
    <!-- Aging report UI -->
  </AppLayout>
</template>
