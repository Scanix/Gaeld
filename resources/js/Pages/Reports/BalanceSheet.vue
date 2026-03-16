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

const asOfDate = ref(props.report.as_of_date)

function applyFilter() {
  router.get('/reports/balance-sheet', { as_of_date: asOfDate.value }, { preserveState: true })
}

const accountColumns = [
  { key: 'code', label: 'Code' },
  { key: 'name', label: 'Account' },
  { key: 'balance', label: 'Balance', format: v => formatCurrency(v) },
]

const sections = [
  { key: 'assets', title: 'Assets' },
  { key: 'liabilities', title: 'Liabilities' },
  { key: 'equity', title: 'Equity' },
]
</script>

<template>
  <AppLayout title="Balance Sheet" help-page="reports">
    <div class="mb-6 flex items-end gap-4">
      <FormInput id="as_of_date" v-model="asOfDate" type="date" label="As of Date" />
      <Button @click="applyFilter">Apply</Button>
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
          <p v-else class="text-sm text-muted-foreground">No {{ section.title.toLowerCase() }} entries.</p>
          <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
            <span>Total {{ section.title }}</span>
            <span>{{ formatCurrency(report[section.key]?.total ?? 0) }}</span>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
