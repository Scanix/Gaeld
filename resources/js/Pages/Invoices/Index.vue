<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { Plus, Pencil, Trash2, Copy, Eye } from 'lucide-vue-next'
import HelpText from '@/Components/HelpText.vue'
import { ref, computed } from 'vue'

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

function daysOverdue(dueDate) {
  if (!dueDate) return 0
  const diff = new Date() - new Date(dueDate)
  return Math.floor(diff / (1000 * 60 * 60 * 24))
}

function isInvoiceOverdue(row) {
  return row.status === 'sent' && row.due_date && new Date(row.due_date) < new Date()
}

const props = defineProps({
  invoices: Object,
  query: {
    type: Object,
    default: () => ({ sort: 'issue_date', direction: 'desc', search: '', filter: {} }),
  },
})

const deleteTarget = ref(null)
const deleting = ref(false)

function confirmDelete(invoice) {
  deleteTarget.value = invoice
}

function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  router.delete(`/invoices/${deleteTarget.value.id}`, {
    onFinish: () => {
      deleting.value = false
      deleteTarget.value = null
    },
  })
}

function duplicate(invoice) {
  router.post(`/invoices/${invoice.id}/duplicate`)
}

function applyQuery(params) {
  router.get('/invoices', {
    ...props.query,
    ...params,
    page: 1,
  }, { preserveState: true, replace: true })
}

function handleSort({ sort, direction }) {
  applyQuery({ sort, direction })
}

function handleSearch(search) {
  applyQuery({ search })
}

function handleFilter({ key, value }) {
  applyQuery({ filter: { ...props.query.filter, [key]: value } })
}

const columns = computed(() => [
  { key: 'number', label: t('number'), sortable: true },
  { key: 'customer', label: t('client'), format: (v) => v?.name ?? '—' },
  { key: 'issue_date', label: t('date'), format: (v) => formatDate(v), sortable: true },
  { key: 'due_date', label: t('due'), format: (v) => formatDate(v), sortable: true },
  { key: 'total', label: t('total'), class: 'text-right', format: (v) => formatCurrency(v), sortable: true },
  { key: 'status', label: t('status'), sortable: true },
  { key: 'actions', label: '', class: 'text-right w-40' },
])

const statusVariant = {
  draft: 'secondary',
  sent: 'default',
  paid: 'success',
  overdue: 'destructive',
  cancelled: 'outline',
}

const statusFilters = computed(() => [
  {
    key: 'status',
    label: t('all_statuses'),
    value: props.query.filter?.status ?? '',
    options: [
      { value: 'draft', label: t('invoice_status_draft') },
      { value: 'sent', label: t('invoice_status_sent') },
      { value: 'paid', label: t('invoice_status_paid') },
      { value: 'overdue', label: t('invoice_status_overdue') },
      { value: 'cancelled', label: t('invoice_status_cancelled') },
    ],
  },
  {
    key: 'type',
    label: t('all_types'),
    value: props.query.filter?.type ?? '',
    options: [
      { value: 'invoice', label: t('invoice') },
      { value: 'credit_note', label: t('credit_note') },
    ],
  },
])
</script>

<template>
  <AppLayout :title="t('invoices')" help-page="invoices">
    <HelpText :title="t('help_invoices_title')" class="mb-6">
      <p>{{ t('help_invoices_text') }}</p>
    </HelpText>

    <div class="mb-6 flex items-center justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('manage_invoices') }}
      </p>
      <Button as="a" href="/invoices/create">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('new_invoice') }}
      </Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="invoices?.data ?? []"
      :pagination="invoices"
      :row-link="(row) => `/invoices/${row.id}`"
      :empty-message="t('no_invoices_yet')"
      :sort="query.sort"
      :direction="query.direction"
      searchable
      :search-value="query.search"
      :filters="statusFilters"
      @sort="handleSort"
      @search="handleSearch"
      @filter="handleFilter"
    >
      <template #cell-status="{ value }">
        <Badge :variant="statusVariant[value] ?? 'secondary'">
          {{ t(`invoice_status_${value}`) }}
        </Badge>
      </template>
      <template #cell-due_date="{ value, row }">
        <span class="flex flex-wrap items-center gap-1.5">
          {{ formatDate(value) }}
          <Badge v-if="isInvoiceOverdue(row)" variant="destructive" class="text-xs">
            {{ t('overdue_days', { days: daysOverdue(value) }) }}
          </Badge>
        </span>
      </template>
      <template #cell-number="{ value, row }">
        <span class="flex items-center gap-1.5">
          {{ value }}
          <Badge v-if="row.type === 'credit_note'" variant="destructive" class="text-xs">
            {{ t('credit_note') }}
          </Badge>
        </span>
      </template>
      <template #cell-actions="{ row }">
        <div class="flex justify-end gap-1">
          <Button
            as="a"
            :href="`/invoices/${row.id}`"
            variant="ghost"
            size="icon"
            :title="t('view')"
            @click.stop
          >
            <Eye class="h-4 w-4" />
          </Button>
          <Button
            v-if="row.status === 'draft'"
            as="a"
            :href="`/invoices/${row.id}/edit`"
            variant="ghost"
            size="icon"
            :title="t('edit')"
            @click.stop
          >
            <Pencil class="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            :title="t('duplicate')"
            @click.stop="duplicate(row)"
          >
            <Copy class="h-4 w-4" />
          </Button>
          <Button
            v-if="row.status === 'draft'"
            variant="ghost"
            size="icon"
            :title="t('delete')"
            @click.stop="confirmDelete(row)"
          >
            <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
          </Button>
        </div>
      </template>
    </DataTable>

    <ConfirmDialog
      :open="!!deleteTarget"
      :title="t('delete_invoice')"
      :message="t('delete_invoice_confirm', { number: deleteTarget?.number })"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
  </AppLayout>
</template>
