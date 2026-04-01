<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Badge from '@/Components/UI/Badge.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useTranslations } from '@/lib/useTranslations'
import { Plus, Eye, Pencil, Trash2, UserPlus } from 'lucide-vue-next'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { ref, computed } from 'vue'

const { t } = useTranslations()

const props = defineProps({
  employees: Object,
  query: {
    type: Object,
    default: () => ({ sort: 'last_name', direction: 'asc', search: '' }),
  },
})

const deleteTarget = ref(null)
const deleting = ref(false)

function confirmDelete(employee) {
  deleteTarget.value = employee
}

function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  router.delete(`/payroll/employees/${deleteTarget.value.id}`, {
    onFinish: () => {
      deleting.value = false
      deleteTarget.value = null
    },
  })
}

function applyQuery(params) {
  router.get('/payroll/employees', { ...props.query, ...params, page: 1 }, { preserveState: true, replace: true })
}

const columns = computed(() => [
  { key: 'full_name', label: t('name'), sortable: true },
  { key: 'ahv_number', label: t('ahv_number') },
  { key: 'position', label: t('position') },
  { key: 'gross_salary', label: t('gross_salary'), class: 'text-right' },
  { key: 'status', label: t('status') },
  { key: 'actions', label: '', class: 'text-right w-28' },
])
</script>

<template>
  <AppLayout :title="t('employees')" help-page="payroll">
    <div class="mb-6 flex items-center justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('manage_employees') }}
      </p>
      <Button as="a" href="/payroll/employees/create">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('new_employee') }}
      </Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="employees?.data ?? []"
      :pagination="employees"
      :sort="query.sort"
      :direction="query.direction"
      :search="query.search"
      :search-placeholder="t('search_employees')"
      @sort="({ sort, direction }) => applyQuery({ sort, direction })"
      @search="(search) => applyQuery({ search })"
    >
      <template #cell-full_name="{ row }">
        {{ row.first_name }} {{ row.last_name }}
      </template>
      <template #cell-ahv_number="{ row }">
        <span class="font-mono text-sm">{{ row.ahv_number }}</span>
      </template>
      <template #cell-gross_salary="{ row }">
        <span class="font-mono">CHF {{ Number(row.gross_salary).toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
      </template>
      <template #cell-status="{ row }">
        <Badge :variant="row.status === 'active' ? 'default' : 'secondary'">
          {{ t('employee_status_' + row.status) }}
        </Badge>
      </template>
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-end gap-2">
          <Link :href="`/payroll/employees/${row.id}`" class="text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
            <Eye class="h-4 w-4" />
          </Link>
          <Link :href="`/payroll/employees/${row.id}/edit`" class="text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
            <Pencil class="h-4 w-4" />
          </Link>
          <button class="text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--destructive))]" @click="confirmDelete(row)">
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </template>
      <template #empty>
        <EmptyState :icon="UserPlus" :title="t('no_employees_yet')" :description="t('no_employees_yet_desc')" :action-label="t('new_employee')" action-href="/payroll/employees/create" />
      </template>
    </DataTable>

    <ConfirmDialog
      :open="!!deleteTarget"
      :title="t('delete_employee')"
      :message="t('delete_employee_confirm')"
      :loading="deleting"
      variant="destructive"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
  </AppLayout>
</template>
