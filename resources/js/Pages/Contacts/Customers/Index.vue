<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { Plus, Pencil, Trash2, Eye, Users } from 'lucide-vue-next'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { ref, computed } from 'vue'

const { t } = useTranslations()

const props = defineProps({
  customers: Object,
  query: {
    type: Object,
    default: () => ({ sort: 'name', direction: 'asc', search: '', filter: {} }),
  },
})

const deleteTarget = ref(null)
const deleting = ref(false)

function confirmDelete(customer) {
  deleteTarget.value = customer
}

function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  router.delete(`/customers/${deleteTarget.value.uuid}`, {
    onFinish: () => {
      deleting.value = false
      deleteTarget.value = null
    },
  })
}

function applyQuery(params) {
  router.get('/customers', {
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
  { key: 'currency', label: t('currency') },
  { key: 'actions', label: '', class: 'text-right w-32' },
])

const countryFilters = computed(() => [
  {
    key: 'country',
    label: t('all_countries'),
    value: props.query.filter?.country ?? '',
    options: [
      { value: 'CH', label: t('country_switzerland') },
      { value: 'DE', label: t('country_germany') },
      { value: 'AT', label: t('country_austria') },
      { value: 'FR', label: t('country_france') },
      { value: 'IT', label: t('country_italy') },
    ],
  },
])
</script>

<template>
  <AppLayout :title="t('customers')" help-page="customers">
    <HelpText :title="t('help_customers_title')" class="mb-6">
      <p>{{ t('help_customers_text') }}</p>
    </HelpText>

    <div class="mb-6 flex items-center justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('manage_customers') }}
      </p>
      <Button as="a" href="/customers/create">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('new_customer') }}
      </Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="customers?.data ?? []"
      :pagination="customers"
      :row-link="(row) => `/customers/${row.uuid}`"
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
            :href="`/customers/${row.uuid}`"
            variant="ghost"
            size="icon"
            :title="t('view')"
            @click.stop
          >
            <Eye class="h-4 w-4" />
          </Button>
          <Button
            as="a"
            :href="`/customers/${row.uuid}/edit`"
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
      <template #empty>
        <EmptyState :icon="Users" :title="t('no_customers_yet')" :description="t('no_customers_yet_desc')" :action-label="t('new_customer')" action-href="/customers/create" />
      </template>
    </DataTable>

    <ConfirmDialog
      :open="!!deleteTarget"
      :title="t('delete_customer')"
      :message="t('confirm_delete_customer', { name: deleteTarget?.name ?? '' })"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
  </AppLayout>
</template>
