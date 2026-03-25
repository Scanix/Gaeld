<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { Plus, Pencil, Trash2, Eye } from 'lucide-vue-next'
import { ref, computed } from 'vue'

const { t } = useTranslations()

const props = defineProps({
  suppliers: Object,
  query: {
    type: Object,
    default: () => ({ sort: 'name', direction: 'asc', search: '', filter: {} }),
  },
})

const deleteTarget = ref(null)
const deleting = ref(false)

function confirmDelete(supplier) {
  deleteTarget.value = supplier
}

function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  router.delete(`/suppliers/${deleteTarget.value.id}`, {
    onFinish: () => {
      deleting.value = false
      deleteTarget.value = null
    },
  })
}

function applyQuery(params) {
  router.get('/suppliers', {
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
  { key: 'name', label: t('name'), sortable: true },
  { key: 'email', label: t('email'), sortable: true },
  { key: 'city', label: t('city'), sortable: true },
  { key: 'country', label: t('country'), sortable: true },
  { key: 'default_expense_category', label: t('category') },
  { key: 'actions', label: '', class: 'text-right w-32' },
])

const countryFilters = computed(() => [
  {
    key: 'country',
    label: t('all_countries'),
    value: props.query.filter?.country ?? '',
    options: [
      { value: 'CH', label: 'Switzerland' },
      { value: 'DE', label: 'Germany' },
      { value: 'AT', label: 'Austria' },
      { value: 'FR', label: 'France' },
      { value: 'IT', label: 'Italy' },
    ],
  },
])
</script>

<template>
  <AppLayout :title="t('suppliers')" help-page="suppliers">
    <HelpText :title="t('help_suppliers_title')" class="mb-6">
      <p>{{ t('help_suppliers_text') }}</p>
    </HelpText>

    <div class="mb-6 flex items-center justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('manage_suppliers') }}
      </p>
      <Button as="a" href="/suppliers/create">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('new_supplier') }}
      </Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="suppliers?.data ?? []"
      :pagination="suppliers"
      :row-link="(row) => `/suppliers/${row.id}`"
      :empty-message="t('no_suppliers_yet')"
      :sort="query.sort"
      :direction="query.direction"
      searchable
      :search-value="query.search"
      :filters="countryFilters"
      @sort="handleSort"
      @search="handleSearch"
      @filter="handleFilter"
    >
      <template #cell-actions="{ row }">
        <div class="flex justify-end gap-1">
          <Button
            as="a"
            :href="`/suppliers/${row.id}`"
            variant="ghost"
            size="icon"
            :title="t('view')"
            @click.stop
          >
            <Eye class="h-4 w-4" />
          </Button>
          <Button
            as="a"
            :href="`/suppliers/${row.id}/edit`"
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
      :title="t('delete_supplier')"
      :message="`Are you sure you want to delete ${deleteTarget?.name}?`"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
  </AppLayout>
</template>
