<script setup>
import { computed } from 'vue'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { TrendingUp, TrendingDown, ArrowRightLeft, Wallet } from 'lucide-vue-next'
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

const props = defineProps({
  revenue: { type: Number, default: 0 },
  expenses: { type: Number, default: 0 },
  balance: { type: Number, default: 0 },
  recentTransactions: { type: Array, default: () => [] },
  monthlyData: { type: Object, default: () => ({ labels: [], revenue: [], expenses: [] }) },
})

const profit = computed(() => props.revenue - props.expenses)

const summaryCards = computed(() => [
  { title: 'Revenue', value: formatCurrency(props.revenue), icon: TrendingUp, color: 'text-green-600' },
  { title: 'Expenses', value: formatCurrency(props.expenses), icon: TrendingDown, color: 'text-red-600' },
  { title: 'Profit', value: formatCurrency(profit.value), icon: Wallet, color: profit.value >= 0 ? 'text-green-600' : 'text-red-600' },
  { title: 'Transactions', value: props.recentTransactions.length, icon: ArrowRightLeft, color: 'text-blue-600' },
])

const chartData = computed(() => ({
  labels: props.monthlyData.labels?.length ? props.monthlyData.labels : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
  datasets: [
    {
      label: 'Revenue',
      data: props.monthlyData.revenue?.length ? props.monthlyData.revenue : [0, 0, 0, 0, 0, 0],
      backgroundColor: 'hsl(142 71% 45% / 0.8)',
      borderRadius: 4,
    },
    {
      label: 'Expenses',
      data: props.monthlyData.expenses?.length ? props.monthlyData.expenses : [0, 0, 0, 0, 0, 0],
      backgroundColor: 'hsl(0 84% 60% / 0.8)',
      borderRadius: 4,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom' },
  },
  scales: {
    y: { beginAtZero: true },
  },
}

const transactionColumns = [
  { key: 'date', label: 'Date', format: (v) => formatDate(v) },
  { key: 'description', label: 'Description' },
  { key: 'reference', label: 'Reference' },
  { key: 'amount', label: 'Amount', class: 'text-right', format: (v) => formatCurrency(v) },
]
</script>

<template>
  <AppLayout title="Dashboard" help-page="getting-started">
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

    <!-- Chart -->
    <Card class="mt-6">
      <CardHeader>
        <CardTitle>Revenue vs Expenses</CardTitle>
        <CardDescription>Monthly comparison for the current fiscal year</CardDescription>
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
        <CardTitle>Recent Transactions</CardTitle>
        <CardDescription>Latest journal entries</CardDescription>
      </CardHeader>
      <CardContent>
        <DataTable
          :columns="transactionColumns"
          :rows="recentTransactions"
          empty-message="No transactions yet. Create an invoice or expense to get started."
        />
      </CardContent>
    </Card>
  </AppLayout>
</template>
