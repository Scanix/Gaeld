<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { Plus, Pencil, Trash2, Copy } from 'lucide-vue-next'
import HelpText from '@/Components/HelpText.vue'
import { ref, computed } from 'vue'

const { t } = useTranslations()

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
  { key: 'actions', label: '', class: 'text-right w-32' },
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
      { value: 'draft', label: 'Draft' },
      { value: 'sent', label: 'Sent' },
      { value: 'paid', label: 'Paid' },
      { value: 'overdue', label: 'Overdue' },
      { value: 'cancelled', label: 'Cancelled' },
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
          {{ value }}
        </Badge>
      </template>
      <template #cell-actions="{ row }">
        <div class="flex justify-end gap-1">
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
