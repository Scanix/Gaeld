<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { Plus, Pencil, Trash2, Eye, Receipt } from 'lucide-vue-next'
import HelpText from '@/Components/HelpText.vue'
import QuickReceiptButton from '@/Components/QuickReceiptButton.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { ref, computed } from 'vue'

const props = defineProps({
  expenses: Object,
  query: {
    type: Object,
    default: () => ({ sort: 'date', direction: 'desc', search: '', filter: {} }),
  },
})

const deleteTarget = ref(null)
const deleting = ref(false)

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

function confirmDelete(expense) {
  deleteTarget.value = expense
}

function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  router.delete(`/expenses/${deleteTarget.value.id}`, {
    onFinish: () => {
      deleting.value = false
      deleteTarget.value = null
    },
  })
}

function applyQuery(params) {
  router.get('/expenses', {
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
  { key: 'date', label: t('date'), format: (v) => formatDate(v), sortable: true },
  { key: 'category', label: t('category'), sortable: true },
  { key: 'description', label: t('description') },
  { key: 'vendor', label: t('vendor'), sortable: true },
  { key: 'amount', label: t('amount'), class: 'text-right', format: (v) => formatCurrency(v), sortable: true },
  { key: 'status', label: t('status'), sortable: true },
  { key: 'actions', label: '', class: 'text-right w-auto' },
])

const statusVariant = {
  pending: 'warning',
  approved: 'info',
  posted: 'success',
}

const statusFilters = computed(() => [
  {
    key: 'status',
    label: t('all_statuses'),
    value: props.query.filter?.status ?? '',
    options: [
      { value: 'pending', label: t('expense_status_pending') },
      { value: 'approved', label: t('expense_status_approved') },
      { value: 'posted', label: t('expense_status_posted') },
    ],
  },
])
</script>

<template>
  <AppLayout :title="t('expenses')" help-page="expenses">
    <HelpText :title="t('help_expenses_title')" class="mb-6">
      <p>{{ t('help_expenses_text') }}</p>
    </HelpText>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('track_expenses') }}
      </p>
      <Button as="a" href="/expenses/create">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('new_expense') }}
      </Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="expenses?.data ?? []"
      :pagination="expenses"
      :row-link="(row) => `/expenses/${row.id}`"
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
          {{ t(`expense_status_${value}`) }}
        </Badge>
      </template>
      <template #cell-actions="{ row }">
        <div class="flex justify-end gap-1">
          <Button
            as="a"
            :href="`/expenses/${row.id}`"
            variant="ghost"
            size="icon"
            :aria-label="t('view')"
            :title="t('view')"
            @click.stop
          >
            <Eye class="h-4 w-4" />
          </Button>
          <Button
            v-if="row.status !== 'posted'"
            as="a"
            :href="`/expenses/${row.id}/edit`"
            variant="ghost"
            size="icon"
            :aria-label="t('edit')"
            :title="t('edit')"
            @click.stop
          >
            <Pencil class="h-4 w-4" />
          </Button>
          <Button
            v-if="row.status === 'pending'"
            variant="ghost"
            size="icon"
            :aria-label="t('delete')"
            :title="t('delete')"
            @click.stop="confirmDelete(row)"
          >
            <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
          </Button>
        </div>
      </template>
      <template #empty>
        <EmptyState :icon="Receipt" :title="t('no_expenses_yet')" :description="t('no_expenses_yet_desc')" :action-label="t('new_expense')" action-href="/expenses/create" />
      </template>
    </DataTable>

    <ConfirmDialog
      :open="!!deleteTarget"
      :title="t('delete_expense')"
      :message="t('delete_expense_confirm')"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />

    <QuickReceiptButton />
  </AppLayout>
</template>
