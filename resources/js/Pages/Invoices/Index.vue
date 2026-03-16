<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { Plus } from 'lucide-vue-next'

const props = defineProps({
  invoices: Object,
})

const columns = [
  { key: 'number', label: 'Number' },
  { key: 'client', label: 'Client', format: (v) => v?.name ?? '—' },
  { key: 'issue_date', label: 'Date', format: (v) => formatDate(v) },
  { key: 'due_date', label: 'Due', format: (v) => formatDate(v) },
  { key: 'total', label: 'Total', class: 'text-right', format: (v) => formatCurrency(v) },
  { key: 'status', label: 'Status' },
]

const statusVariant = {
  draft: 'secondary',
  sent: 'default',
  paid: 'outline',
  overdue: 'destructive',
}
</script>

<template>
  <AppLayout title="Invoices" help-page="invoices">
    <div class="mb-6 flex items-center justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        Manage and track your client invoices.
      </p>
      <Button as="a" href="/invoices/create">
        <Plus class="mr-2 h-4 w-4" />
        New Invoice
      </Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="invoices?.data ?? []"
      :pagination="invoices"
      :row-link="(row) => `/invoices/${row.id}`"
      empty-message="No invoices yet. Create your first invoice to get started."
    >
      <template #cell-status="{ value }">
        <Badge :variant="statusVariant[value] ?? 'secondary'">
          {{ value }}
        </Badge>
      </template>
    </DataTable>
  </AppLayout>
</template>
