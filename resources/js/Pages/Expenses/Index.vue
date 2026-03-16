<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { Plus } from 'lucide-vue-next'

const props = defineProps({
  expenses: Object,
})

const columns = [
  { key: 'date', label: 'Date', format: (v) => formatDate(v) },
  { key: 'category', label: 'Category' },
  { key: 'description', label: 'Description' },
  { key: 'vendor', label: 'Vendor' },
  { key: 'amount', label: 'Amount', class: 'text-right', format: (v) => formatCurrency(v) },
  { key: 'status', label: 'Status' },
]

const statusVariant = {
  draft: 'secondary',
  posted: 'default',
}
</script>

<template>
  <AppLayout title="Expenses" help-page="expenses">
    <div class="mb-6 flex items-center justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        Track and categorize your business expenses.
      </p>
      <Button as="a" href="/expenses/create">
        <Plus class="mr-2 h-4 w-4" />
        New Expense
      </Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="expenses?.data ?? []"
      :pagination="expenses"
      :row-link="(row) => `/expenses/${row.id}`"
      empty-message="No expenses recorded yet."
    >
      <template #cell-status="{ value }">
        <Badge :variant="statusVariant[value] ?? 'secondary'">
          {{ value }}
        </Badge>
      </template>
    </DataTable>
  </AppLayout>
</template>
