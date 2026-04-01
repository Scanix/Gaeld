<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { TrendingUp, TrendingDown, ArrowRightLeft, Wallet, X, AlertTriangle, Receipt, Target, Settings, GripVertical, Eye, EyeOff, ChevronUp, ChevronDown } from 'lucide-vue-next'
import HelpText from '@/Components/HelpText.vue'
import QuickReceiptButton from '@/Components/QuickReceiptButton.vue'
import AccountingChecklist from '@/Components/AccountingChecklist.vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const props = defineProps({
  revenue: { type: Number, default: 0 },
  expenses: { type: Number, default: 0 },
  balance: { type: Number, default: 0 },
  cashBalance: { type: Number, default: 0 },
  unpaidInvoices: { type: Object, default: () => ({ count: 0, total: 0 }) },
  pendingExpenses: { type: Object, default: () => ({ count: 0, total: 0 }) },
  previousRevenue: { type: Number, default: 0 },
  previousExpenses: { type: Number, default: 0 },
  previousBalance: { type: Number, default: 0 },
  budgetSummary: { type: Object, default: null },
  vatSummary: { type: Object, default: null },
  receivablesAging: { type: Object, default: null },
  recentTransactions: { type: Array, default: () => [] },
  monthlyBreakdown: { type: Object, default: () => ({ labels: [], revenue: [], expenses: [], forecast: [], revenueItems: [], expenseItems: [], forecastItems: [] }) },
  checklist: { type: Array, default: () => [] },
  dashboardLayout: { type: Object, default: null },
})

// ── Widget layout management ──────────────────────────────────────────
const defaultWidgets = [
  { id: 'checklist', visible: true },
  { id: 'action_cards', visible: true },
  { id: 'budget', visible: true },
  { id: 'chart', visible: true },
  { id: 'transactions', visible: true },
]

const widgetLabels = {
  checklist: 'accounting_checklist',
  action_cards: 'action_cards_widget',
  budget: 'budget_vs_actual',
  chart: 'revenue_vs_expenses',
  transactions: 'recent_transactions',
}

const showCustomize = ref(false)
const savingLayout = ref(false)

const widgetLayout = ref(
  props.dashboardLayout?.widgets
    ? [...props.dashboardLayout.widgets]
    : defaultWidgets.map((w) => ({ ...w }))
)

function isWidgetVisible(id) {
  const widget = widgetLayout.value.find((w) => w.id === id)
  return widget ? widget.visible : true
}

function toggleWidget(id) {
  const widget = widgetLayout.value.find((w) => w.id === id)
  if (widget) widget.visible = !widget.visible
}

function moveWidget(index, direction) {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= widgetLayout.value.length) return
  const items = [...widgetLayout.value]
  const [moved] = items.splice(index, 1)
  items.splice(newIndex, 0, moved)
  widgetLayout.value = items
}

function saveLayout() {
  savingLayout.value = true
  router.put('/profile/dashboard-layout', {
    widgets: widgetLayout.value,
  }, {
    preserveScroll: true,
    onFinish: () => {
      savingLayout.value = false
      showCustomize.value = false
    },
  })
}

function resetLayout() {
  widgetLayout.value = defaultWidgets.map((w) => ({ ...w }))
}

const profit = computed(() => props.revenue - props.expenses)
const previousProfit = computed(() => props.previousRevenue - props.previousExpenses)

function yoyChange(current, previous) {
  if (!previous || previous === 0) return null
  return ((current - previous) / Math.abs(previous) * 100).toFixed(1)
}

const summaryCards = computed(() => [
  { title: t('cash_balance'), value: formatCurrency(props.cashBalance), icon: Wallet, color: props.cashBalance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400', trend: null },
  { title: t('revenue'), value: formatCurrency(props.revenue), icon: TrendingUp, color: 'text-green-600 dark:text-green-400', trend: yoyChange(props.revenue, props.previousRevenue) },
  { title: t('expenses'), value: formatCurrency(props.expenses), icon: TrendingDown, color: 'text-red-600 dark:text-red-400', trend: yoyChange(props.expenses, props.previousExpenses) },
  { title: t('profit'), value: formatCurrency(profit.value), icon: ArrowRightLeft, color: profit.value >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400', trend: yoyChange(profit.value, previousProfit.value) },
])

const chartData = computed(() => ({
  labels: props.monthlyBreakdown.labels?.length ? props.monthlyBreakdown.labels : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
  datasets: [
    {
      label: t('revenue'),
      data: props.monthlyBreakdown.revenue?.length ? props.monthlyBreakdown.revenue : [0, 0, 0, 0, 0, 0],
      backgroundColor: 'hsl(142 71% 45% / 0.8)',
      borderRadius: 4,
    },
    {
      label: t('expenses'),
      data: props.monthlyBreakdown.expenses?.length ? props.monthlyBreakdown.expenses : [0, 0, 0, 0, 0, 0],
      backgroundColor: 'hsl(0 84% 60% / 0.8)',
      borderRadius: 4,
    },
    {
      label: t('forecast'),
      data: props.monthlyBreakdown.forecast?.length ? props.monthlyBreakdown.forecast : [0, 0, 0, 0, 0, 0],
      backgroundColor: 'hsl(45 93% 58% / 0.7)',
      borderRadius: 4,
      borderColor: 'hsl(45 93% 47% / 1)',
      borderWidth: 1,
      borderDash: [4, 4],
    },
  ],
}))

// Month label → JS month index mapping (short month names)
const monthIndexMap = {
  Jan: 0, Feb: 1, Mar: 2, Apr: 3, May: 4, Jun: 5,
  Jul: 6, Aug: 7, Sep: 8, Oct: 9, Nov: 10, Dec: 11,
}

const selectedMonth = ref(null)

function onChartClick(_event, elements, chart) {
  if (!elements.length) return
  const index = elements[0].index
  const label = chart.data.labels[index]
  selectedMonth.value = selectedMonth.value === label ? null : label
}

function clearMonthFilter() {
  selectedMonth.value = null
}

const filteredTransactions = computed(() => {
  if (!selectedMonth.value) return props.recentTransactions
  const mi = monthIndexMap[selectedMonth.value]
  if (mi === undefined) return props.recentTransactions
  const year = new Date().getFullYear()
  return props.recentTransactions.filter((t) => {
    const d = new Date(t.date)
    return d.getFullYear() === year && d.getMonth() === mi
  })
})

const transactionsTitle = computed(() => {
  if (selectedMonth.value) return t('transactions_month', { month: selectedMonth.value, year: new Date().getFullYear() })
  return t('recent_transactions')
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  onClick: onChartClick,
  plugins: {
    legend: { position: 'bottom' },
    tooltip: {
      callbacks: {
        title: (items) => {
          if (!items.length) return ''
          return `${items[0].label} ${new Date().getFullYear()}`
        },
        label: (item) => ` ${item.dataset.label}: ${formatCurrency(item.raw)}`,
        afterBody: (items) => {
          if (!items.length) return ''
          const index = items[0].dataIndex
          const rev = props.monthlyBreakdown.revenue?.[index] ?? 0
          const exp = props.monthlyBreakdown.expenses?.[index] ?? 0
          const fc = props.monthlyBreakdown.forecast?.[index] ?? 0
          const net = rev - exp
          const lines = []

          // Detail items
          const revItems = props.monthlyBreakdown.revenueItems?.[index] ?? []
          const expItems = props.monthlyBreakdown.expenseItems?.[index] ?? []
          const fcItems = props.monthlyBreakdown.forecastItems?.[index] ?? []

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
    y: {
      beginAtZero: true,
      ticks: {
        callback: (value) => formatCurrency(value),
      },
    },
  },
}

const transactionColumns = computed(() => [
  { key: 'date', label: t('date'), format: (v) => formatDate(v) },
  { key: 'description', label: t('description') },
  { key: 'reference', label: t('reference') },
  { key: 'amount', label: t('amount'), class: 'text-right' },
])
</script>

<template>
  <AppLayout :title="t('dashboard')" help-page="getting-started">
    <div class="mb-6 flex items-start justify-between gap-4">
      <HelpText :title="t('help_dashboard_title')">
        <p>{{ t('help_dashboard_text') }}</p>
      </HelpText>
      <button
        @click="showCustomize = !showCustomize"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
      >
        <Settings class="h-3.5 w-3.5" />
        {{ t('customize') }}
      </button>
    </div>

    <!-- Customize Panel -->
    <Card v-if="showCustomize" class="mb-6 border-indigo-200 dark:border-indigo-800">
      <CardHeader>
        <CardTitle>{{ t('customize_dashboard') }}</CardTitle>
        <CardDescription>{{ t('customize_dashboard_description') }}</CardDescription>
      </CardHeader>
      <CardContent>
        <div class="space-y-1">
          <div
            v-for="(widget, index) in widgetLayout"
            :key="widget.id"
            class="flex items-center gap-3 rounded-md px-3 py-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
          >
            <div class="flex flex-col gap-0.5">
              <button
                @click="moveWidget(index, -1)"
                :disabled="index === 0"
                class="text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300"
              >
                <ChevronUp class="h-3.5 w-3.5" />
              </button>
              <button
                @click="moveWidget(index, 1)"
                :disabled="index === widgetLayout.length - 1"
                class="text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300"
              >
                <ChevronDown class="h-3.5 w-3.5" />
              </button>
            </div>
            <GripVertical class="h-4 w-4 text-gray-300 dark:text-gray-600" />
            <button
              @click="toggleWidget(widget.id)"
              class="flex items-center gap-2 text-sm"
            >
              <component :is="widget.visible ? Eye : EyeOff" :class="['h-4 w-4', widget.visible ? 'text-green-600 dark:text-green-400' : 'text-gray-400']" />
              <span :class="widget.visible ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 line-through dark:text-gray-500'">
                {{ t(widgetLabels[widget.id]) }}
              </span>
            </button>
          </div>
        </div>
        <div class="mt-4 flex items-center gap-2">
          <button
            @click="saveLayout"
            :disabled="savingLayout"
            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition-colors hover:bg-indigo-700 disabled:opacity-50"
          >
            {{ savingLayout ? t('saving') : t('save') }}
          </button>
          <button
            @click="resetLayout"
            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            {{ t('reset') }}
          </button>
        </div>
      </CardContent>
    </Card>

    <!-- Summary Cards (always visible) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card v-for="card in summaryCards" :key="card.title">
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardDescription>{{ card.title }}</CardDescription>
          <component :is="card.icon" :class="['h-4 w-4', card.color]" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ card.value }}</div>
          <p v-if="card.trend !== null" :class="['mt-1 text-xs', parseFloat(card.trend) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400']">
            {{ parseFloat(card.trend) >= 0 ? '+' : '' }}{{ card.trend }}% {{ t('vs_last_year') }}
          </p>
        </CardContent>
      </Card>
    </div>

    <!-- Dynamic widget sections rendered in user-defined order -->
    <template v-for="widget in widgetLayout" :key="widget.id">

      <!-- Accounting Checklist -->
      <div v-if="widget.id === 'checklist' && widget.visible && checklist.length" class="mt-6">
        <AccountingChecklist :items="checklist" />
      </div>

      <!-- Action Cards -->
      <div v-if="widget.id === 'action_cards' && widget.visible" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Card v-if="unpaidInvoices.count > 0" class="border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/30">
          <CardContent class="flex items-center justify-between pt-6">
            <div>
              <p class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ t('unpaid_invoice', { count: unpaidInvoices.count }) }}</p>
              <p class="text-lg font-bold text-amber-900 dark:text-amber-100">{{ formatCurrency(unpaidInvoices.total) }}</p>
            </div>
            <a href="/invoices" class="text-sm font-medium text-amber-700 hover:underline dark:text-amber-300">{{ t('view') }}</a>
          </CardContent>
        </Card>
        <Card v-if="pendingExpenses.count > 0" class="border-blue-200 bg-blue-50/50 dark:border-blue-800 dark:bg-blue-950/30">
          <CardContent class="flex items-center justify-between pt-6">
            <div>
              <p class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ t('pending_expense', { count: pendingExpenses.count }) }}</p>
              <p class="text-lg font-bold text-blue-900 dark:text-blue-100">{{ formatCurrency(pendingExpenses.total) }}</p>
            </div>
            <a href="/expenses" class="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300">{{ t('view') }}</a>
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
      <Card v-if="widget.id === 'budget' && widget.visible && budgetSummary" class="mt-4">
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
                  :class="parseFloat(budgetSummary.revenueVariance) >= 0 ? 'bg-green-500' : 'bg-amber-500'"
                  :style="{ width: Math.min(100, parseFloat(budgetSummary.proRatedRevenue) > 0 ? (parseFloat(budgetSummary.actualRevenue) / parseFloat(budgetSummary.proRatedRevenue) * 100) : 0) + '%' }"
                />
              </div>
              <p class="mt-0.5 text-xs" :class="parseFloat(budgetSummary.revenueVariance) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'">
                {{ parseFloat(budgetSummary.revenueVariance) >= 0 ? '+' : '' }}{{ budgetSummary.revenueVariance }}% {{ parseFloat(budgetSummary.revenueVariance) >= 0 ? t('on_track') : t('behind_target') }}
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
                  :class="parseFloat(budgetSummary.expenseVariance) <= 0 ? 'bg-green-500' : 'bg-red-500'"
                  :style="{ width: Math.min(100, parseFloat(budgetSummary.proRatedExpenses) > 0 ? (parseFloat(budgetSummary.actualExpenses) / parseFloat(budgetSummary.proRatedExpenses) * 100) : 0) + '%' }"
                />
              </div>
              <p class="mt-0.5 text-xs" :class="parseFloat(budgetSummary.expenseVariance) <= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                {{ parseFloat(budgetSummary.expenseVariance) >= 0 ? '+' : '' }}{{ budgetSummary.expenseVariance }}% {{ parseFloat(budgetSummary.expenseVariance) <= 0 ? t('under_budget') : t('over_budget') }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Chart -->
      <Card v-if="widget.id === 'chart' && widget.visible" class="mt-6">
        <CardHeader>
          <CardTitle>{{ t('revenue_vs_expenses') }}</CardTitle>
          <CardDescription>{{ t('monthly_comparison') }} — {{ t('click_bar_filter') }}</CardDescription>
        </CardHeader>
        <CardContent>
          <div class="h-72">
            <Bar :data="chartData" :options="chartOptions" />
          </div>
        </CardContent>
      </Card>

      <!-- Recent Transactions -->
      <Card v-if="widget.id === 'transactions' && widget.visible" class="mt-6">
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>{{ transactionsTitle }}</CardTitle>
              <CardDescription>{{ selectedMonth ? t('showing_entries_for', { month: selectedMonth }) : t('latest_journal_entries') }}</CardDescription>
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
            :empty-message="selectedMonth ? t('no_transactions_in_month', { month: selectedMonth }) : t('no_transactions_yet')"
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

    </template>

    <QuickReceiptButton />
  </AppLayout>
</template>
