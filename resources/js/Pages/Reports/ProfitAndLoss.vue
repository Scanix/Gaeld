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
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed, watch } from 'vue'
import HelpText from '@/Components/HelpText.vue'

const props = defineProps({ report: Object })

const from = ref(props.report.period.from)
const to = ref(props.report.period.to)

// Comparison toggle
const compareEnabled = ref(!!props.report.comparison)
const compareFrom = ref(props.report.comparison?.period?.from ?? '')
const compareTo = ref(props.report.comparison?.period?.to ?? '')

// Default comparison: same period last year
watch([from, to], ([newFrom, newTo]) => {
  if (!compareFrom.value && !compareTo.value) {
    compareFrom.value = newFrom ? `${parseInt(newFrom.slice(0, 4)) - 1}${newFrom.slice(4)}` : ''
    compareTo.value = newTo ? `${parseInt(newTo.slice(0, 4)) - 1}${newTo.slice(4)}` : ''
  }
}, { immediate: true })

function applyFilter() {
  const params = { from: from.value, to: to.value }
  if (compareEnabled.value && compareFrom.value && compareTo.value) {
    params.compare_from = compareFrom.value
    params.compare_to = compareTo.value
  }
  router.get('/reports/profit-and-loss', params, { preserveState: true })
}

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const hasBudget = computed(() => !!props.report.budgets && Object.keys(props.report.budgets).length > 0)

const accountColumns = computed(() => {
  const cols = [
    { key: 'code', label: t('code') },
    { key: 'name', label: t('account') },
    { key: 'balance', label: t('current_period'), class: 'text-right', format: v => formatCurrency(v) },
  ]
  if (compareEnabled.value && props.report.comparison) {
    cols.push(
      { key: '_compare', label: t('comparison_period'), class: 'text-right' },
      { key: '_variance', label: t('variance'), class: 'text-right' },
      { key: '_variance_pct', label: t('variance_pct'), class: 'text-right' },
    )
  }
  if (hasBudget.value) {
    cols.push(
      { key: '_budget', label: t('budget'), class: 'text-right' },
      { key: '_budget_variance', label: t('budget_variance'), class: 'text-right' },
    )
  }
  return cols
})

// Merge comparison data into each row
function mergeComparison(rows, compRows, isExpense = false) {
  const compMap = {}
  if (compRows) compRows.forEach(r => { compMap[r.code] = r })
  const budgetMap = props.report.budgets ?? {}
  return rows.map(row => {
    const merged = { ...row }
    if (compRows) {
      const comp = compMap[row.code]
      const compBalance = Number(comp?.balance ?? 0)
      const currentBalance = Number(row.balance)
      const variance = currentBalance - compBalance
      const variancePct = compBalance !== 0 ? (variance / Math.abs(compBalance)) * 100 : null
      const positiveIsGood = !isExpense
      const varianceClass = variance === 0 ? '' : (variance > 0 ? (positiveIsGood ? 'text-green-600' : 'text-red-600') : (positiveIsGood ? 'text-red-600' : 'text-green-600'))
      merged._compare = formatCurrency(compBalance)
      merged._variance = formatCurrency(variance)
      merged._variance_pct = variancePct !== null ? `${variancePct.toFixed(1)}%` : '—'
      merged._variance_class = varianceClass
    }
    if (hasBudget.value) {
      const budgetAmount = budgetMap[row.code] ?? 0
      const budgetVariance = row.balance - budgetAmount
      const bvClass = budgetVariance >= 0
        ? (isExpense ? 'text-red-600' : 'text-green-600')
        : (isExpense ? 'text-green-600' : 'text-red-600')
      merged._budget = formatCurrency(budgetAmount)
      merged._budget_variance = formatCurrency(budgetVariance)
      merged._budget_variance_class = bvClass
    }
    return merged
  })
}
</script>

<template>
  <AppLayout :title="t('profit_and_loss')" help-page="reports">
    <HelpText :title="t('help_profit_loss_title')" class="mb-6">
      <p>{{ t('help_profit_loss_text') }}</p>
    </HelpText>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
      <div class="flex flex-wrap items-end gap-4">
        <FormInput id="from" v-model="from" type="date" :label="t('from')" />
        <FormInput id="to" v-model="to" type="date" :label="t('to')" />
        <Button @click="applyFilter">{{ t('apply') }}</Button>
      </div>
      <ExportDropdown base-url="/reports/profit-and-loss/export" :params="{ from, to }" />
    </div>

    <!-- Comparison toggle -->
    <div class="mb-6 space-y-3">
      <label class="flex cursor-pointer items-center gap-2 text-sm">
        <input v-model="compareEnabled" type="checkbox" class="h-4 w-4 rounded border-[hsl(var(--input))]" />
        {{ t('compare_with') }}
      </label>
      <div v-if="compareEnabled" class="flex flex-wrap items-end gap-4">
        <FormInput id="compare_from" v-model="compareFrom" type="date" :label="t('compare_from')" />
        <FormInput id="compare_to" v-model="compareTo" type="date" :label="t('compare_to')" />
      </div>
    </div>

    <div class="space-y-6">
      <Card>
        <CardHeader><CardTitle>{{ t('revenue') }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable
            v-if="report.revenue.length"
            :columns="accountColumns"
            :rows="mergeComparison(report.revenue, report.comparison?.revenue, false)"
          />
          <p v-else class="text-sm text-muted-foreground">{{ t('no_revenue_entries') }}</p>
          <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>{{ t('total_revenue') }}</span>
            <div class="flex gap-8">
              <span>{{ formatCurrency(report.total_revenue) }}</span>
              <span v-if="compareEnabled && report.comparison" class="text-[hsl(var(--muted-foreground))]">
                {{ formatCurrency(report.comparison.total_revenue ?? 0) }}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{{ t('expenses') }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable
            v-if="report.expenses.length"
            :columns="accountColumns"
            :rows="mergeComparison(report.expenses, report.comparison?.expenses, true)"
          />
          <p v-else class="text-sm text-muted-foreground">{{ t('no_expense_entries') }}</p>
          <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>{{ t('total_expenses') }}</span>
            <div class="flex gap-8">
              <span>{{ formatCurrency(report.total_expenses) }}</span>
              <span v-if="compareEnabled && report.comparison" class="text-[hsl(var(--muted-foreground))]">
                {{ formatCurrency(report.comparison.total_expenses ?? 0) }}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <div class="flex justify-between text-lg font-bold">
            <span>{{ t('net_profit') }}</span>
            <div class="flex gap-8">
              <span :class="report.net_profit >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ formatCurrency(report.net_profit) }}
              </span>
              <span
                v-if="compareEnabled && report.comparison"
                :class="(report.comparison.net_profit ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'"
                class="opacity-70"
              >
                {{ formatCurrency(report.comparison.net_profit ?? 0) }}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

