<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useEntityIndexQuery, useCountryFilters, useEntityDelete } from '@/lib/useEntityIndexTable'
import { Plus, Pencil, Trash2, Eye, Users } from 'lucide-vue-next'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { computed } from 'vue'

const { t } = useTranslations()

const props = defineProps({
  customers: Object,
  query: {
    type: Object,
    default: () => ({ sort: 'name', direction: 'asc', search: '', filter: {} }),
  },
})

const queryState = computed(() => props.query)

const { handleSort, handleSearch, handleFilter } = useEntityIndexQuery({
  basePath: '/customers',
  query: queryState,
})

const { deleteTarget, deleting, confirmDelete, executeDelete } = useEntityDelete({
  basePath: '/customers',
})

const columns = computed(() => [
  { key: 'name', label: t('name'), sortable: true },
  { key: 'email', label: t('email'), sortable: true },
  { key: 'city', label: t('city'), sortable: true },
  { key: 'country', label: t('country'), sortable: true },
  { key: 'currency', label: t('currency') },
  { key: 'actions', label: '', class: 'text-right w-auto' },
])

const countryFilters = useCountryFilters({ t, query: queryState })
</script>

<template>
  <AppLayout :title="t('customers')" help-page="customers">
    <HelpText :title="t('help_customers_title')" class="mb-6">
      <p>{{ t('help_customers_text') }}</p>
    </HelpText>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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
            :aria-label="t('view') + ' ' + row.name"
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
            :aria-label="t('edit') + ' ' + row.name"
            :title="t('edit')"
            @click.stop
          >
            <Pencil class="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            :aria-label="t('delete') + ' ' + row.name"
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
