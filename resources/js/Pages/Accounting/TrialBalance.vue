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
import { ref, computed } from 'vue'

const props = defineProps({
  balances: Array,
  asOfDate: String,
})

const date = ref(props.asOfDate)

function applyFilter() {
  router.get('/accounting/trial-balance', { as_of_date: date.value }, { preserveState: true })
}

const columns = [
  { key: 'code', label: 'Code' },
  { key: 'name', label: 'Account' },
  { key: 'debit', label: 'Debit', format: v => v > 0 ? formatCurrency(v) : '' },
  { key: 'credit', label: 'Credit', format: v => v > 0 ? formatCurrency(v) : '' },
]

const totalDebit = computed(() => (props.balances || []).reduce((s, b) => s + (b.debit || 0), 0))
const totalCredit = computed(() => (props.balances || []).reduce((s, b) => s + (b.credit || 0), 0))
</script>

<template>
  <AppLayout title="Trial Balance" help-page="accounting-basics">
    <div class="mb-6 flex items-end gap-4">
      <FormInput id="as_of_date" v-model="date" type="date" label="As of Date" />
      <Button @click="applyFilter">Apply</Button>
    </div>

    <Card>
      <CardHeader><CardTitle>Trial Balance</CardTitle></CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="balances || []" />
        <div class="mt-4 flex justify-between border-t pt-3 text-sm font-semibold">
          <span>Totals</span>
          <div class="flex gap-12">
            <span>{{ formatCurrency(totalDebit) }}</span>
            <span>{{ formatCurrency(totalCredit) }}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  </AppLayout>
</template>
