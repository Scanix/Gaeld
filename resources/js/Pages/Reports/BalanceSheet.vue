<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import Button from '@/Components/UI/Button.vue'
import ExportDropdown from '@/Components/UI/ExportDropdown.vue'
import SharePrintButton from '@/Components/UI/SharePrintButton.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'
import HelpText from '@/Components/HelpText.vue'

const props = defineProps({ report: Object })

const asOfDate = ref(props.report.as_of_date)

function applyFilter() {
  router.get('/reports/balance-sheet', { as_of_date: asOfDate.value }, { preserveState: true })
}

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const accountColumns = computed(() => [
  { key: 'code', label: t('code') },
  { key: 'name', label: t('account') },
  { key: 'balance', label: t('balance'), format: v => formatCurrency(v) },
])

const sections = computed(() => [
  { key: 'assets', title: t('assets') },
  { key: 'liabilities', title: t('liabilities') },
  { key: 'equity', title: t('equity') },
])
</script>

<template>
  <AppLayout :title="t('balance_sheet')" help-page="reports">
    <HelpText :title="t('help_balance_sheet_title')" class="mb-6">
      <p>{{ t('help_balance_sheet_text') }}</p>
    </HelpText>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div class="flex items-end gap-4">
        <FormInput id="as_of_date" v-model="asOfDate" type="date" :label="t('as_of_date')" />
        <Button @click="applyFilter">{{ t('apply') }}</Button>
      </div>
      <div class="flex items-center gap-2">
        <SharePrintButton :title="t('balance_sheet')" />
        <ExportDropdown base-url="/reports/balance-sheet/export" :params="{ as_of_date: asOfDate }" />
      </div>
    </div>

    <div class="space-y-6">
      <Card v-for="section in sections" :key="section.key">
        <CardHeader><CardTitle>{{ section.title }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable
            v-if="report[section.key]?.accounts?.length"
            :columns="accountColumns"
            :rows="report[section.key].accounts"
          />
          <p v-else class="text-sm text-muted-foreground">{{ t('no_section_entries', { section: section.title.toLowerCase() }) }}</p>
          <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>{{ t('total_section', { section: section.title }) }}</span>
            <span>{{ formatCurrency(report[section.key]?.total ?? 0) }}</span>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
