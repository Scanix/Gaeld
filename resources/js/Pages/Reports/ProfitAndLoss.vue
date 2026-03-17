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
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'

const props = defineProps({ report: Object })

const from = ref(props.report.period.from)
const to = ref(props.report.period.to)

function applyFilter() {
  router.get('/reports/profit-and-loss', { from: from.value, to: to.value }, { preserveState: true })
}

const { t } = useTranslations()

const accountColumns = computed(() => [
  { key: 'code', label: t('code') },
  { key: 'name', label: t('account') },
  { key: 'balance', label: t('amount'), format: v => formatCurrency(v) },
])
</script>

<template>
  <AppLayout :title="t('profit_and_loss')" help-page="reports">
    <div class="mb-6 flex flex-wrap items-end gap-4">
      <FormInput id="from" v-model="from" type="date" :label="t('from')" />
      <FormInput id="to" v-model="to" type="date" :label="t('to')" />
      <Button @click="applyFilter">{{ t('apply') }}</Button>
    </div>

    <div class="space-y-6">
      <Card>
        <CardHeader><CardTitle>{{ t('revenue') }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable v-if="report.revenue.length" :columns="accountColumns" :rows="report.revenue" />
          <p v-else class="text-sm text-muted-foreground">{{ t('no_revenue_entries') }}</p>
          <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>{{ t('total_revenue') }}</span>
            <span>{{ formatCurrency(report.total_revenue) }}</span>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{{ t('expenses') }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable v-if="report.expenses.length" :columns="accountColumns" :rows="report.expenses" />
          <p v-else class="text-sm text-muted-foreground">{{ t('no_expense_entries') }}</p>
          <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>{{ t('total_expenses') }}</span>
            <span>{{ formatCurrency(report.total_expenses) }}</span>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <div class="flex justify-between text-lg font-bold">
            <span>{{ t('net_profit') }}</span>
            <span :class="report.net_profit >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ formatCurrency(report.net_profit) }}
            </span>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
