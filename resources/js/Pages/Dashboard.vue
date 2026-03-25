<script setup>
import { computed, ref } from 'vue'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { TrendingUp, TrendingDown, ArrowRightLeft, Wallet, X } from 'lucide-vue-next'
import HelpText from '@/Components/HelpText.vue'
import QuickReceiptButton from '@/Components/QuickReceiptButton.vue'
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

const props = defineProps({
  revenue: { type: Number, default: 0 },
  expenses: { type: Number, default: 0 },
  balance: { type: Number, default: 0 },
  cashBalance: { type: Number, default: 0 },
  unpaidInvoices: { type: Object, default: () => ({ count: 0, total: 0 }) },
  pendingExpenses: { type: Object, default: () => ({ count: 0, total: 0 }) },
  recentTransactions: { type: Array, default: () => [] },
  monthlyBreakdown: { type: Object, default: () => ({ labels: [], revenue: [], expenses: [], forecast: [], revenueItems: [], expenseItems: [], forecastItems: [] }) },
})

const profit = computed(() => props.revenue - props.expenses)

const summaryCards = computed(() => [
  { title: t('cash_balance'), value: formatCurrency(props.cashBalance), icon: Wallet, color: props.cashBalance >= 0 ? 'text-green-600' : 'text-red-600' },
  { title: t('revenue'), value: formatCurrency(props.revenue), icon: TrendingUp, color: 'text-green-600' },
  { title: t('expenses'), value: formatCurrency(props.expenses), icon: TrendingDown, color: 'text-red-600' },
  { title: t('profit'), value: formatCurrency(profit.value), icon: ArrowRightLeft, color: profit.value >= 0 ? 'text-green-600' : 'text-red-600' },
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
    <HelpText :title="t('help_dashboard_title')" class="mb-6">
      <p>{{ t('help_dashboard_text') }}</p>
    </HelpText>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card v-for="card in summaryCards" :key="card.title">
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardDescription>{{ card.title }}</CardDescription>
          <component :is="card.icon" :class="['h-4 w-4', card.color]" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ card.value }}</div>
        </CardContent>
      </Card>
    </div>

    <!-- Action Cards -->
    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Card v-if="unpaidInvoices.count > 0" class="border-amber-200 bg-amber-50/50">
        <CardContent class="flex items-center justify-between pt-6">
          <div>
            <p class="text-sm font-medium text-amber-800">{{ t('unpaid_invoice', { count: unpaidInvoices.count }) }}</p>
            <p class="text-lg font-bold text-amber-900">{{ formatCurrency(unpaidInvoices.total) }}</p>
          </div>
          <a href="/invoices" class="text-sm font-medium text-amber-700 hover:underline">{{ t('view') }}</a>
        </CardContent>
      </Card>
      <Card v-if="pendingExpenses.count > 0" class="border-blue-200 bg-blue-50/50">
        <CardContent class="flex items-center justify-between pt-6">
          <div>
            <p class="text-sm font-medium text-blue-800">{{ t('pending_expense', { count: pendingExpenses.count }) }}</p>
            <p class="text-lg font-bold text-blue-900">{{ formatCurrency(pendingExpenses.total) }}</p>
          </div>
          <a href="/expenses" class="text-sm font-medium text-blue-700 hover:underline">{{ t('view') }}</a>
        </CardContent>
      </Card>
    </div>

    <!-- Chart -->
    <Card class="mt-6">
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
    <Card class="mt-6">
      <CardHeader>
        <div class="flex items-center justify-between">
          <div>
            <CardTitle>{{ transactionsTitle }}</CardTitle>
            <CardDescription>{{ selectedMonth ? t('showing_entries_for', { month: selectedMonth }) : t('latest_journal_entries') }}</CardDescription>
          </div>
          <button
            v-if="selectedMonth"
            @click="clearMonthFilter"
            class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200 transition-colors"
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
            <span :class="['font-medium', row.type === 'income' ? 'text-green-600' : row.type === 'expense' ? 'text-red-600' : 'text-gray-600']">
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
