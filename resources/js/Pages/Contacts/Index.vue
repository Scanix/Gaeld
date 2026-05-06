<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import Badge from '@/Components/UI/Badge.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useEntityIndexQuery, useCountryFilters, useEntityDelete } from '@/lib/useEntityIndexTable'
import { Plus, Pencil, Trash2, Eye, Users } from 'lucide-vue-next'
import EmptyState from '@/Components/UI/EmptyState.vue'

const { t } = useTranslations()

const props = defineProps({
  contacts: Object,
  query: {
    type: Object,
    default: () => ({ sort: 'name', direction: 'asc', search: '', filter: {} }),
  },
})

const queryState = computed(() => props.query)

const { handleSort, handleSearch, handleFilter } = useEntityIndexQuery({
  basePath: '/contacts',
  query: queryState,
})

const { deleteTarget, deleting, confirmDelete, executeDelete } = useEntityDelete({
  basePath: '/contacts',
})

const columns = computed(() => [
  { key: 'name', label: t('name'), sortable: true },
  { key: 'roles', label: t('roles') },
  { key: 'email', label: t('email'), sortable: true },
  { key: 'city', label: t('city'), sortable: true },
  { key: 'country', label: t('country'), sortable: true },
  { key: 'currency', label: t('currency') },
  { key: 'actions', label: '', class: 'text-right w-auto' },
])

const countryFilters = useCountryFilters({ t, query: queryState })
</script>

<template>
  <AppLayout :title="t('contacts')" help-page="contacts">
    <HelpText :title="t('help_contacts_title')" class="mb-6">
      <p>{{ t('help_contacts_text') }}</p>
    </HelpText>

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">
        {{ t('manage_contacts') }}
      </p>
      <Button as="a" href="/contacts/create">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('new_contact') }}
      </Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="contacts?.data ?? []"
      :pagination="contacts"
      :row-link="(row) => `/contacts/${row.uuid}`"
      :sort="query.sort"
      :direction="query.direction"
      searchable
      :search-value="query.search"
      :filters="countryFilters"
      @sort="handleSort"
      @search="handleSearch"
      @filter="handleFilter"
    >
      <template #cell-roles="{ row }">
        <div class="flex gap-1 flex-wrap">
          <Badge v-if="row.is_customer" variant="default" class="text-xs">{{ t('customer') }}</Badge>
          <Badge v-if="row.is_supplier" variant="secondary" class="text-xs">{{ t('supplier') }}</Badge>
        </div>
      </template>
      <template #cell-actions="{ row }">
        <div class="flex justify-end gap-1">
          <Button
            as="a"
            :href="`/contacts/${row.uuid}`"
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
            :href="`/contacts/${row.uuid}/edit`"
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
        <EmptyState
          :icon="Users"
          :title="t('no_contacts_yet')"
          :description="t('no_contacts_yet_desc')"
          :action-label="t('new_contact')"
          action-href="/contacts/create"
        />
      </template>
    </DataTable>

    <ConfirmDialog
      :open="!!deleteTarget"
      :title="t('delete_contact')"
      :message="t('confirm_delete_contact', { name: deleteTarget?.name ?? '' })"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
  </AppLayout>
</template>
