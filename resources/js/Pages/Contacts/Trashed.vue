<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { RotateCcw, Trash2 } from 'lucide-vue-next'

const { t } = useTranslations()
const { formatDate } = useFormatters()

defineProps({
  contacts: Object,
})

function restore(row) {
  router.post(`/contacts/${row.uuid}/restore`)
}

const columns = computed(() => [
  { key: 'name', label: t('name') },
  { key: 'email', label: t('email') },
  { key: 'deleted_at', label: t('deleted_at'), format: (v) => formatDate(v) },
  { key: 'actions', label: '', class: 'text-right w-auto' },
])
</script>

<template>
  <AppLayout :title="t('trashed_contacts')" help-page="contacts">
    <Breadcrumb :items="[{ label: t('contacts'), href: '/contacts' }, { label: t('trashed_contacts') }]" class="mb-4" />

    <p class="mb-6 text-sm text-[hsl(var(--muted-foreground))]">
      {{ t('trashed_contacts_desc') }}
    </p>

    <DataTable :columns="columns" :rows="contacts?.data ?? []" :pagination="contacts">
      <template #cell-actions="{ row }">
        <div class="flex justify-end gap-1">
          <Button
            variant="ghost"
            size="icon"
            :aria-label="t('restore') + ' ' + row.name"
            :title="t('restore')"
            @click.stop="restore(row)"
          >
            <RotateCcw class="h-4 w-4" />
          </Button>
        </div>
      </template>
      <template #empty>
        <EmptyState :icon="Trash2" :title="t('no_trashed_contacts')" />
      </template>
    </DataTable>
  </AppLayout>
</template>
