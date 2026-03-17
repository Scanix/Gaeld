<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'
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
  { key: 'actions', label: '', class: 'text-right w-24' },
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
      { value: 'pending', label: 'Pending' },
      { value: 'approved', label: 'Approved' },
      { value: 'posted', label: 'Posted' },
    ],
  },
])
</script>

<template>
  <AppLayout :title="t('expenses')" help-page="expenses">
    <div class="mb-6 flex items-center justify-between">
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
      :empty-message="t('no_expenses_yet')"
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
            v-if="row.status !== 'posted'"
            as="a"
            :href="`/expenses/${row.id}/edit`"
            variant="ghost"
            size="icon"
            :title="t('edit')"
            @click.stop
          >
            <Pencil class="h-4 w-4" />
          </Button>
          <Button
            v-if="row.status === 'pending'"
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
      :title="t('delete_expense')"
      :message="t('delete_expense_confirm')"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
  </AppLayout>
</template>
