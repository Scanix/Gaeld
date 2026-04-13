<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { Plus, Pencil, Trash2, Pause, Play, RefreshCw } from 'lucide-vue-next'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { ref, computed } from 'vue'

const { t } = useTranslations()
const { formatDate } = useFormatters()

const props = defineProps({
  recurringInvoices: Object,
})

const deleteTarget = ref(null)
const deleting = ref(false)

function confirmDelete(item) {
  deleteTarget.value = item
}

function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  router.delete(`/invoices/recurring/${deleteTarget.value.uuid}`, {
    onFinish: () => {
      deleting.value = false
      deleteTarget.value = null
    },
  })
}

function togglePause(item) {
  if (!item.is_active) {
    router.post(`/invoices/recurring/${item.uuid}/resume`)
  } else {
    router.post(`/invoices/recurring/${item.uuid}/pause`)
  }
}

const statusVariant = {
  active: 'success',
  paused: 'warning',
}

const columns = computed(() => [
  { key: 'customer', label: t('client'), format: (v) => v?.name ?? '—' },
  { key: 'frequency', label: t('frequency'), format: (v) => t(`frequency_${v}`) },
  { key: 'next_issue_date', label: t('next_issue_date'), format: (v) => v ? formatDate(v) : '—' },
  { key: 'is_active', label: t('status') },
  { key: 'actions', label: '', class: 'text-right w-auto' },
])
</script>

<template>
  <AppLayout :title="t('recurring_invoices')">
    <div class="mb-6 flex items-center justify-between">
      <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('recurring_invoices') }}</p>
      <Button as="a" href="/invoices/recurring/create">
        <Plus class="mr-2 h-4 w-4" />
        {{ t('new_recurring_invoice') }}
      </Button>
    </div>

    <Card>
      <CardHeader><CardTitle>{{ t('recurring_invoices') }}</CardTitle></CardHeader>
      <CardContent>
        <DataTable
          :columns="columns"
          :rows="recurringInvoices?.data ?? []"
          :pagination="recurringInvoices"
        >
          <template #cell-is_active="{ row }">
            <Badge :variant="row.is_active ? 'success' : 'warning'">
              {{ t(row.is_active ? 'recurring_status_active' : 'recurring_status_paused') }}
            </Badge>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-1">
              <Button
                as="a"
                :href="`/invoices/recurring/${row.uuid}/edit`"
                variant="ghost"
                size="icon"
                :title="t('edit')"
              >
                <Pencil class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                :title="row.is_active ? t('pause') : t('resume')"
                @click="togglePause(row)"
              >
                <Pause v-if="row.is_active" class="h-4 w-4" />
                <Play v-else class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                :title="t('delete')"
                @click="confirmDelete(row)"
              >
                <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </Button>
            </div>
          </template>
          <template #empty>
            <EmptyState :icon="RefreshCw" :title="t('no_recurring_invoices')" :description="t('no_recurring_invoices_desc')" :action-label="t('new_recurring_invoice')" action-href="/invoices/recurring/create" />
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <ConfirmDialog
      :open="!!deleteTarget"
      :title="t('delete')"
      :message="t('are_you_sure')"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
  </AppLayout>
</template>
