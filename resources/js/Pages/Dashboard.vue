<script setup>
import { computed, ref } from 'vue'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { useTheme } from '@/lib/useTheme'
import { TrendingUp, TrendingDown, ArrowRightLeft, Wallet, X, AlertTriangle, Receipt, Target, ScanLine, Clock } from 'lucide-vue-next'
import HelpText from '@/Components/HelpText.vue'
import QuickReceiptButton from '@/Components/QuickReceiptButton.vue'
import { normalizeDashboardContract } from '@/lib/inertiaContracts'
import { Bar } from 'vue-chartjs'
import { Link } from '@inertiajs/vue3'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'
import { intlLocale } from '@/lib/utils'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const { t } = useTranslations()
const { formatCurrency, formatDate, intlMonthName, locale } = useFormatters()
const { isDark } = useTheme()

const props = defineProps({
  revenue: { type: [Number, String], default: 0 },
  expenses: { type: [Number, String], default: 0 },
  balance: { type: [Number, String], default: 0 },
  cashBalance: { type: [Number, String], default: 0 },
  unpaidInvoices: { type: Object, default: () => ({ count: 0, total: 0 }) },
  pendingExpenses: { type: Object, default: () => ({ count: 0, total: 0 }) },
  previousRevenue: { type: [Number, String], default: 0 },
  previousExpenses: { type: [Number, String], default: 0 },
  previousBalance: { type: [Number, String], default: 0 },
  hasPreviousYearData: { type: Boolean, default: false },
  budgetSummary: { type: Object, default: null },
  vatSummary: { type: Object, default: null },
  receivablesAging: { type: Object, default: null },
  recentTransactions: { type: Array, default: () => [] },
  monthlyBreakdown: { type: Object, default: () => ({ monthIndices: [], revenue: [], expenses: [], forecast: [], revenueItems: [], expenseItems: [], forecastItems: [] }) },
  pendingOcrScans: { type: Number, default: 0 },
  displayYear: { type: Number, default: () => new Date().getFullYear() },
  isEmptyState: { type: Boolean, default: false },
  hasExportModule: { type: Boolean, default: false },
  expiredFiscalYear: { type: Object, default: null },
})

const contract = computed(() => normalizeDashboardContract(props))

function asNumber(value) {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

const profit = computed(() => asNumber(contract.value.revenue) - asNumber(contract.value.expenses))
const previousProfit = computed(() => asNumber(contract.value.previousRevenue) - asNumber(contract.value.previousExpenses))

function yoyChange(current, previous) {
  // Only compare against the previous year when the org actually had
  // activity then; otherwise the percentage is meaningless (e.g. -100% or
  // unbounded growth from a near-zero baseline).
  if (!contract.value.hasPreviousYearData) return null
  const prev = Number(previous)
  const cur = Number(current)
  if (!prev || !Number.isFinite(prev) || !Number.isFinite(cur)) return null
  const result = (cur - prev) / Math.abs(prev) * 100
  return Number.isFinite(result) ? result.toFixed(1) : null
}

function safePercent(val) {
  const n = parseFloat(val)
  return Number.isFinite(n) ? n : 0
}

const summaryCards = computed(() => [
  { title: t('cash_balance'), value: formatCurrency(contract.value.cashBalance), icon: Wallet, color: asNumber(contract.value.cashBalance) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400', trend: null },
  { title: t('revenue'), value: formatCurrency(contract.value.revenue), icon: TrendingUp, color: 'text-green-600 dark:text-green-400', trend: yoyChange(contract.value.revenue, contract.value.previousRevenue) },
  { title: t('expenses'), value: formatCurrency(contract.value.expenses), icon: TrendingDown, color: 'text-red-600 dark:text-red-400', trend: yoyChange(contract.value.expenses, contract.value.previousExpenses) },
  { title: t('profit'), value: formatCurrency(profit.value), icon: ArrowRightLeft, color: profit.value >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400', trend: yoyChange(profit.value, previousProfit.value) },
])

// ── Chart ──────────────────────────────────────────────────────────────

const chartLabels = computed(() => {
  const indices = contract.value.monthlyBreakdown.monthIndices
  if (!indices?.length) {
    return Array.from({ length: 12 }, (_, i) =>
      new Intl.DateTimeFormat(intlLocale(locale.value), { month: 'short' }).format(new Date(2000, i, 1))
    )
  }
  return indices.map((idx) =>
    new Intl.DateTimeFormat(intlLocale(locale.value), { month: 'short' }).format(new Date(2000, idx - 1, 1))
  )
})

const chartData = computed(() => ({
  labels: chartLabels.value,
  datasets: [
    {
      label: t('revenue'),
      data: contract.value.monthlyBreakdown.revenue?.length ? contract.value.monthlyBreakdown.revenue : Array(12).fill(0),
      backgroundColor: 'hsl(142 71% 45% / 0.8)',
      borderRadius: 4,
    },
    {
      label: t('expenses'),
      data: contract.value.monthlyBreakdown.expenses?.length ? contract.value.monthlyBreakdown.expenses : Array(12).fill(0),
      backgroundColor: 'hsl(0 84% 60% / 0.8)',
      borderRadius: 4,
    },
    {
      label: t('forecast'),
      data: contract.value.monthlyBreakdown.forecast?.length ? contract.value.monthlyBreakdown.forecast : Array(12).fill(0),
      backgroundColor: 'hsl(45 93% 58% / 0.3)',
      borderColor: 'hsl(45 93% 47% / 1)',
      borderWidth: 2,
      borderRadius: 4,
    },
  ],
}))

const selectedMonth = ref(null)

function onChartClick(_event, elements) {
  if (!elements.length) return
  const index = elements[0].index
  const monthIndex = contract.value.monthlyBreakdown.monthIndices?.[index] ?? (index + 1)
  selectedMonth.value = selectedMonth.value === monthIndex ? null : monthIndex
}

function clearMonthFilter() {
  selectedMonth.value = null
}

const filteredTransactions = computed(() => {
  if (!selectedMonth.value) return contract.value.recentTransactions
  return contract.value.recentTransactions.filter((transaction) => {
    const d = new Date(transaction.date)
    return d.getFullYear() === contract.value.displayYear && d.getMonth() === selectedMonth.value - 1
  })
})

const transactionsTitle = computed(() => {
  if (selectedMonth.value) {
    return t('transactions_month', { month: intlMonthName(selectedMonth.value - 1), year: contract.value.displayYear })
  }
  return t('recent_transactions')
})

const chartOptions = computed(() => {
  const tickColor = isDark.value ? 'rgba(237,237,237,0.7)' : 'rgba(20,20,20,0.6)'
  const gridColor = isDark.value ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)'

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    onClick: onChartClick,
    plugins: {
      legend: {
        position: 'bottom',
        labels: { color: tickColor },
      },
      tooltip: {
        callbacks: {
          title: (items) => {
            if (!items.length) return ''
            return `${items[0].label} ${contract.value.displayYear}`
          },
          label: (item) => ` ${item.dataset.label}: ${formatCurrency(item.raw)}`,
          afterBody: (items) => {
            if (!items.length) return ''
            const index = items[0].dataIndex
            const rev = contract.value.monthlyBreakdown.revenue?.[index] ?? 0
            const exp = contract.value.monthlyBreakdown.expenses?.[index] ?? 0
            const fc = contract.value.monthlyBreakdown.forecast?.[index] ?? 0
            const net = rev - exp
            const lines = []

            const revItems = contract.value.monthlyBreakdown.revenueItems?.[index] ?? []
            const expItems = contract.value.monthlyBreakdown.expenseItems?.[index] ?? []
            const fcItems = contract.value.monthlyBreakdown.forecastItems?.[index] ?? []

            if (revItems.length) {
              lines.push('', `── ${t('revenue')} ──`)
              revItems.forEach((i) => lines.push(`  ${i}`))
            }
            if (expItems.length) {
              lines.push('', `── ${t('expenses')} ──`)
              expItems.forEach((i) => lines.push(`  ${i}`))
            }
            if (fcItems.length) {
              lines.push('', `── ${t('forecast')} ──`)
              fcItems.forEach((i) => lines.push(`  ${i}`))
            }

            lines.push('')
            const sign = net >= 0 ? t('profit') : t('loss')
            lines.push(`${sign}: ${formatCurrency(Math.abs(net))}`)
            if (fc > 0) {
              lines.push(`${t('with_forecast')}: ${formatCurrency(Math.abs(net + fc))}`)
            }

            return lines
          },
        },
      },
    },
    scales: {
      x: {
        ticks: { color: tickColor },
        grid: { color: gridColor },
      },
      y: {
        beginAtZero: true,
        ticks: {
          color: tickColor,
          callback: (value) => 'CHF ' + new Intl.NumberFormat(intlLocale(locale.value), { notation: 'compact', maximumFractionDigits: 1 }).format(value),
        },
        grid: { color: gridColor },
      },
    },
  }
})

const hasChartData = computed(() => {
  const bd = contract.value.monthlyBreakdown
  return (bd.revenue ?? []).some(v => v > 0) || (bd.expenses ?? []).some(v => v > 0)
})

const transactionColumns = computed(() => [
  { key: 'date', label: t('date'), format: (v) => formatDate(v) },
  { key: 'description', label: t('description') },
  { key: 'reference', label: t('reference') },
  { key: 'amount', label: t('amount'), class: 'text-right' },
])
</script>

<template>
  <AppLayout :title="t('dashboard')" help-page="getting-started">
    <div class="mb-6">
      <HelpText :title="t('help_dashboard_title')">
        <p>{{ t('help_dashboard_text') }}</p>
      </HelpText>
    </div>

    <!-- Summary Cards (always visible) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <StatCard
        v-for="card in summaryCards"
        :key="card.title"
        :title="card.title"
        :value="card.value"
        :icon="card.icon"
        :icon-class="card.color"
        :trend="card.trend"
      />
    </div>

    <!-- Empty state: no activity yet — guide the user to their first action -->
    <div
      v-if="isEmptyState"
      class="mt-6 rounded-lg border-2 border-dashed border-[hsl(var(--border))] p-8 text-center"
    >
      <h3 class="text-lg font-semibold">{{ t('dashboard_empty_state_title') }}</h3>
      <p class="mt-2 text-sm text-[hsl(var(--muted-foreground))]">{{ t('dashboard_empty_state_desc') }}</p>
      <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
        <Link
          href="/invoices/create"
          class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90 transition-colors"
        >
          {{ t('dashboard_create_first_invoice') }}
        </Link>
        <Link
          v-if="hasExportModule"
          href="/accounting/export"
          class="inline-flex items-center rounded-md border border-[hsl(var(--border))] bg-background px-4 py-2 text-sm font-medium hover:bg-accent transition-colors"
        >
          {{ t('dashboard_export_for_accountant') }}
        </Link>
      </div>
    </div>

    <!-- Expired fiscal year banner — prompt the user to close it -->
    <div
      v-if="expiredFiscalYear"
      class="mt-6 rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30 p-4"
    >
      <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
          <Clock class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
          <div>
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
              {{ t('dashboard_fiscal_year_expired_title', { year: expiredFiscalYear.name }) }}
            </p>
            <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
              {{ t('dashboard_fiscal_year_expired_desc') }}
            </p>
          </div>
        </div>
        <Link
          :href="`/accounting/closing?fiscal_year_id=${expiredFiscalYear.id}`"
          class="shrink-0 inline-flex items-center rounded-md bg-amber-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-700 transition-colors"
        >
          {{ t('dashboard_close_year') }}
        </Link>
      </div>
    </div>

    <!-- OCR Receipts — pending validation (shown only when there are pending scans) -->
    <Card v-if="pendingOcrScans > 0" class="mt-6 border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/30">
      <CardContent class="flex items-center justify-between pt-6">
        <div>
          <div class="flex items-center gap-2">
            <ScanLine class="h-4 w-4 text-amber-600 dark:text-amber-400" />
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ t('ocr_pending_widget_title') }}</p>
          </div>
          <p class="mt-1 text-lg font-bold text-amber-900 dark:text-amber-100">
            {{ pendingOcrScans }} {{ t('ocr_pending_widget_description') }}
          </p>
        </div>
        <a href="/expenses" class="text-sm font-medium text-amber-700 hover:underline dark:text-amber-300">{{ t('view') }}</a>
      </CardContent>
    </Card>

    <!-- Action Cards -->
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Card v-if="unpaidInvoices.count > 0" class="border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/30">
        <CardContent class="flex items-center justify-between pt-6">
          <div>
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ t('unpaid_invoice', { count: unpaidInvoices.count }) }}</p>
            <p class="text-lg font-bold text-amber-900 dark:text-amber-100">{{ formatCurrency(unpaidInvoices.total) }}</p>
          </div>
          <a href="/invoices?filter[status]=sent" class="text-sm font-medium text-amber-700 hover:underline dark:text-amber-300">{{ t('view') }}</a>
        </CardContent>
      </Card>
      <Card v-if="pendingExpenses.count > 0" class="border-blue-200 bg-blue-50/50 dark:border-blue-800 dark:bg-blue-950/30">
        <CardContent class="flex items-center justify-between pt-6">
          <div>
            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ t('pending_expense', { count: pendingExpenses.count }) }}</p>
            <p class="text-lg font-bold text-blue-900 dark:text-blue-100">{{ formatCurrency(pendingExpenses.total) }}</p>
          </div>
          <a href="/expenses?filter[status]=pending" class="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300">{{ t('view') }}</a>
        </CardContent>
      </Card>

      <!-- Receivables Aging Alert -->
      <Card v-if="receivablesAging" class="border-red-200 bg-red-50/50 dark:border-red-800 dark:bg-red-950/30">
        <CardContent class="pt-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="flex items-center gap-2">
                <AlertTriangle class="h-4 w-4 text-red-600 dark:text-red-400" />
                <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ t('overdue_receivables', { count: receivablesAging.overdueCount }) }}</p>
              </div>
              <p class="mt-1 text-lg font-bold text-red-900 dark:text-red-100">{{ formatCurrency(receivablesAging.totalOverdue) }}</p>
              <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-red-700 dark:text-red-300">
                <span v-if="parseFloat(receivablesAging.brackets['1_30']) > 0">1–30d: {{ formatCurrency(receivablesAging.brackets['1_30']) }}</span>
                <span v-if="parseFloat(receivablesAging.brackets['31_60']) > 0">31–60d: {{ formatCurrency(receivablesAging.brackets['31_60']) }}</span>
                <span v-if="parseFloat(receivablesAging.brackets['61_90']) > 0">61–90d: {{ formatCurrency(receivablesAging.brackets['61_90']) }}</span>
                <span v-if="parseFloat(receivablesAging.brackets['90_plus']) > 0">90d+: {{ formatCurrency(receivablesAging.brackets['90_plus']) }}</span>
              </div>
            </div>
            <a href="/reports/aging" class="text-sm font-medium text-red-700 hover:underline dark:text-red-300">{{ t('view') }}</a>
          </div>
        </CardContent>
      </Card>

      <!-- VAT Liability -->
      <Card v-if="vatSummary" class="border-purple-200 bg-purple-50/50 dark:border-purple-800 dark:bg-purple-950/30">
        <CardContent class="flex items-center justify-between pt-6">
          <div>
            <div class="flex items-center gap-2">
              <Receipt class="h-4 w-4 text-purple-600 dark:text-purple-400" />
              <p class="text-sm font-medium text-purple-800 dark:text-purple-200">{{ t('vat_due_quarter', { quarter: vatSummary.quarterLabel }) }}</p>
            </div>
            <p class="mt-1 text-lg font-bold text-purple-900 dark:text-purple-100">{{ formatCurrency(vatSummary.vatPayable) }}</p>
          </div>
          <a href="/reports/vat" class="text-sm font-medium text-purple-700 hover:underline dark:text-purple-300">{{ t('view') }}</a>
        </CardContent>
      </Card>
    </div>

    <!-- Budget vs Actual -->
    <Card v-if="budgetSummary" class="mt-4">
      <CardHeader>
        <div class="flex items-center gap-2">
          <Target class="h-4 w-4 text-gray-500 dark:text-gray-400" />
          <CardTitle>{{ t('budget_vs_actual') }}</CardTitle>
        </div>
        <CardDescription>{{ t('budget_ytd_description', { months: budgetSummary.monthsElapsed }) }}</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="space-y-4">
          <!-- Revenue progress -->
          <div>
            <div class="mb-1 flex items-center justify-between text-sm">
              <span class="font-medium text-gray-700 dark:text-gray-300">{{ t('revenue') }}</span>
              <span class="text-gray-500 dark:text-gray-400">{{ formatCurrency(budgetSummary.actualRevenue) }} / {{ formatCurrency(budgetSummary.proRatedRevenue) }}</span>
            </div>
            <div class="h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
              <div
                class="h-2.5 rounded-full transition-all"
                :class="safePercent(budgetSummary.revenueVariance) >= 0 ? 'bg-green-500' : 'bg-amber-500'"
                :style="{ width: Math.min(100, safePercent(budgetSummary.proRatedRevenue) > 0 ? (safePercent(budgetSummary.actualRevenue) / safePercent(budgetSummary.proRatedRevenue) * 100) : 0) + '%' }"
              />
            </div>
            <p class="mt-0.5 text-xs" :class="safePercent(budgetSummary.revenueVariance) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'">
              {{ safePercent(budgetSummary.revenueVariance) >= 0 ? '+' : '' }}{{ safePercent(budgetSummary.revenueVariance).toFixed(1) }}% {{ safePercent(budgetSummary.revenueVariance) >= 0 ? t('on_track') : t('behind_target') }}
            </p>
          </div>
          <!-- Expenses progress -->
          <div>
            <div class="mb-1 flex items-center justify-between text-sm">
              <span class="font-medium text-gray-700 dark:text-gray-300">{{ t('expenses') }}</span>
              <span class="text-gray-500 dark:text-gray-400">{{ formatCurrency(budgetSummary.actualExpenses) }} / {{ formatCurrency(budgetSummary.proRatedExpenses) }}</span>
            </div>
            <div class="h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
              <div
                class="h-2.5 rounded-full transition-all"
                :class="safePercent(budgetSummary.expenseVariance) <= 0 ? 'bg-green-500' : 'bg-red-500'"
                :style="{ width: Math.min(100, safePercent(budgetSummary.proRatedExpenses) > 0 ? (safePercent(budgetSummary.actualExpenses) / safePercent(budgetSummary.proRatedExpenses) * 100) : 0) + '%' }"
              />
            </div>
            <p class="mt-0.5 text-xs" :class="safePercent(budgetSummary.expenseVariance) <= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
              {{ safePercent(budgetSummary.expenseVariance) >= 0 ? '+' : '' }}{{ safePercent(budgetSummary.expenseVariance).toFixed(1) }}% {{ safePercent(budgetSummary.expenseVariance) <= 0 ? t('under_budget') : t('over_budget') }}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Chart -->
    <Card class="mt-6">
      <CardHeader>
        <CardTitle>{{ t('revenue_vs_expenses') }}</CardTitle>
        <CardDescription>{{ t('monthly_comparison') }} {{ contract.displayYear }} — {{ t('click_bar_filter') }}</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="h-96">
          <Bar v-if="hasChartData" :data="chartData" :options="chartOptions" />
          <div v-else class="flex h-full items-center justify-center">
            <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('no_data') }}</p>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Recent Transactions -->
    <Card class="mt-6">
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle>{{ transactionsTitle }}</CardTitle>
            <CardDescription>{{ selectedMonth ? t('showing_entries_for', { month: intlMonthName(selectedMonth - 1) }) : t('latest_journal_entries') }}</CardDescription>
          </div>
          <button
            v-if="selectedMonth"
            @click="clearMonthFilter"
            class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 transition-colors dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            <X class="h-3 w-3" />
            {{ t('clear_filter') }}
          </button>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable
          :columns="transactionColumns"
          :rows="filteredTransactions"
          :empty-message="selectedMonth ? t('no_transactions_in_month', { month: intlMonthName(selectedMonth - 1) }) : t('no_transactions_yet')"
        >
          <template #cell-amount="{ row }">
            <span :class="['font-medium', row.type === 'income' ? 'text-green-600 dark:text-green-400' : row.type === 'expense' ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400']">
              <template v-if="row.type === 'income'">+</template>
              <template v-else-if="row.type === 'expense'">&minus;</template>
              {{ formatCurrency(row.amount) }}
            </span>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <QuickReceiptButton />
  </AppLayout>
</template>
