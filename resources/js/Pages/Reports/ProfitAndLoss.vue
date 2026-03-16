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
import { formatCurrency } from '@/lib/utils'
import { ref } from 'vue'

const props = defineProps({ report: Object })

const from = ref(props.report.period.from)
const to = ref(props.report.period.to)

function applyFilter() {
  router.get('/reports/profit-and-loss', { from: from.value, to: to.value }, { preserveState: true })
}

const accountColumns = [
  { key: 'code', label: 'Code' },
  { key: 'name', label: 'Account' },
  { key: 'balance', label: 'Amount', format: v => formatCurrency(v) },
]
</script>

<template>
  <AppLayout title="Profit & Loss" help-page="reports">
    <div class="mb-6 flex flex-wrap items-end gap-4">
      <FormInput id="from" v-model="from" type="date" label="From" />
      <FormInput id="to" v-model="to" type="date" label="To" />
      <Button @click="applyFilter">Apply</Button>
    </div>

    <div class="space-y-6">
      <Card>
        <CardHeader><CardTitle>Revenue</CardTitle></CardHeader>
        <CardContent>
          <DataTable v-if="report.revenue.length" :columns="accountColumns" :rows="report.revenue" />
          <p v-else class="text-sm text-muted-foreground">No revenue entries for this period.</p>
          <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>Total Revenue</span>
            <span>{{ formatCurrency(report.total_revenue) }}</span>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Expenses</CardTitle></CardHeader>
        <CardContent>
          <DataTable v-if="report.expenses.length" :columns="accountColumns" :rows="report.expenses" />
          <p v-else class="text-sm text-muted-foreground">No expense entries for this period.</p>
          <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>Total Expenses</span>
            <span>{{ formatCurrency(report.total_expenses) }}</span>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <div class="flex justify-between text-lg font-bold">
            <span>Net Profit</span>
            <span :class="report.net_profit >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ formatCurrency(report.net_profit) }}
            </span>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
