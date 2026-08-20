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
import { ref, computed, watch } from 'vue'
import HelpText from '@/Components/HelpText.vue'

const props = defineProps({ report: Object })

const asOfDate = ref(props.report.as_of_date)

// Comparison toggle
const compareEnabled = ref(!!props.report.comparison)
const compareAsOfDate = ref(props.report.comparison?.as_of_date ?? '')

// Default comparison: same date last year
watch(asOfDate, (newAsOfDate) => {
  if (!compareAsOfDate.value && newAsOfDate) {
    compareAsOfDate.value = `${parseInt(newAsOfDate.slice(0, 4)) - 1}${newAsOfDate.slice(4)}`
  }
}, { immediate: true })

function applyFilter() {
  const params = { as_of_date: asOfDate.value }
  if (compareEnabled.value && compareAsOfDate.value) {
    params.compare_as_of_date = compareAsOfDate.value
  }
  router.get('/reports/balance-sheet', params, { preserveState: true })
}

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const accountColumns = computed(() => {
  const cols = [
    { key: 'code', label: t('code') },
    { key: 'name', label: t('account') },
    { key: 'balance', label: t('balance'), class: 'text-right', format: v => formatCurrency(v) },
  ]
  if (compareEnabled.value && props.report.comparison) {
    cols.push(
      { key: '_compare', label: t('comparison_period'), class: 'text-right' },
      { key: '_variance', label: t('variance'), class: 'text-right' },
    )
  }
  return cols
})

const sections = computed(() => [
  { key: 'assets', title: t('assets') },
  { key: 'liabilities', title: t('liabilities') },
  { key: 'equity', title: t('equity') },
])

// Merge comparison balances into each row by account code
function mergeComparison(section) {
  const rows = props.report[section]?.accounts ?? []
  if (!compareEnabled.value || !props.report.comparison) {
    return rows
  }

  const compRows = props.report.comparison[section]?.accounts ?? []
  const compMap = {}
  compRows.forEach(r => { compMap[r.code] = r })

  return rows.map(row => {
    const comp = compMap[row.code]
    const compBalance = Number(comp?.balance ?? 0)
    const currentBalance = Number(row.balance ?? 0)
    const variance = currentBalance - compBalance

    return {
      ...row,
      _compare: formatCurrency(compBalance),
      _variance: formatCurrency(variance),
    }
  })
}
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

    <!-- Comparison toggle -->
    <div class="mb-6 space-y-3">
      <label class="flex cursor-pointer items-center gap-2 text-sm">
        <input v-model="compareEnabled" type="checkbox" class="h-4 w-4 rounded border-[hsl(var(--input))]" />
        {{ t('compare_with') }}
      </label>
      <div v-if="compareEnabled" class="flex flex-wrap items-end gap-4">
        <FormInput id="compare_as_of_date" v-model="compareAsOfDate" type="date" :label="t('compare_as_of_date')" />
        <Button @click="applyFilter">{{ t('apply') }}</Button>
      </div>
    </div>

    <div class="space-y-6">
      <Card v-for="section in sections" :key="section.key">
        <CardHeader><CardTitle>{{ section.title }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable
            v-if="report[section.key]?.accounts?.length"
            :columns="accountColumns"
            :rows="mergeComparison(section.key)"
          />
          <p v-else class="text-sm text-muted-foreground">{{ t('no_section_entries', { section: section.title.toLowerCase() }) }}</p>
          <div class="mt-4 flex flex-col gap-1 border-t pt-3 text-sm font-semibold sm:flex-row sm:justify-between">
            <span>{{ t('total_section', { section: section.title }) }}</span>
            <div class="flex gap-4 sm:gap-8">
              <span>{{ formatCurrency(report[section.key]?.total ?? 0) }}</span>
              <span v-if="compareEnabled && report.comparison" class="text-[hsl(var(--muted-foreground))]">
                {{ formatCurrency(report.comparison[section.key]?.total ?? 0) }}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
