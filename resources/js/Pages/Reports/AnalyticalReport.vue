<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import Button from '@/Components/UI/Button.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'

const props = defineProps({
  report: Object,
  costCenters: Array,
  filters: Object,
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const from = ref(props.filters.from)
const to = ref(props.filters.to)
const costCenterId = ref(props.filters?.cost_center_id ? String(props.filters.cost_center_id) : '')

const costCenterOptions = computed(() => [
  { value: '', label: t('all') },
  ...props.costCenters.map(cc => ({ value: cc.id, label: `${cc.code} — ${cc.name}` })),
])

function applyFilter() {
  const params = { from: from.value, to: to.value }
  if (costCenterId.value) params.cost_center_id = costCenterId.value
  router.get('/accounting/analytical-report', params, { preserveState: true })
}

const columns = [
  { key: 'code', label: t('code') },
  { key: 'name', label: t('account') },
  { key: 'balance', label: t('balance'), class: 'text-right', format: v => formatCurrency(v) },
]
</script>

<template>
  <AppLayout :title="t('analytical_report')">
    <p class="mb-4 text-sm text-[hsl(var(--muted-foreground))]">{{ t('analytical_report_desc') }}</p>

    <div class="mb-6 flex flex-wrap items-end gap-4">
      <FormInput id="from" v-model="from" type="date" :label="t('from')" />
      <FormInput id="to" v-model="to" type="date" :label="t('to')" />
      <FormSelect id="cost_center_id" v-model="costCenterId" :label="t('cost_center')" :options="costCenterOptions" />
      <Button @click="applyFilter">{{ t('apply') }}</Button>
    </div>

    <div class="space-y-6">
      <Card>
        <CardHeader><CardTitle>{{ t('revenue') }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable v-if="report.revenue?.length" :columns="columns" :rows="report.revenue" />
          <p v-else class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('no_data') }}</p>
          <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>{{ t('total_revenue') }}</span>
            <span>{{ formatCurrency(report.total_revenue) }}</span>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>{{ t('expenses') }}</CardTitle></CardHeader>
        <CardContent>
          <DataTable v-if="report.expenses?.length" :columns="columns" :rows="report.expenses" />
          <p v-else class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('no_data') }}</p>
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
