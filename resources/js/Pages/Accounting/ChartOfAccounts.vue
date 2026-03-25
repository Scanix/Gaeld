<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import AccountFormDialog from '@/Components/AccountFormDialog.vue'
import AccountImportDialog from '@/Components/AccountImportDialog.vue'
import { useTranslations } from '@/lib/useTranslations'
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import HelpText from '@/Components/HelpText.vue'
import { Plus, Upload, Download, Pencil, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  accounts: Array,
  can: Object,
  accountTypes: Array,
})

const { t } = useTranslations()

const typeVariant = {
  asset: 'info',
  liability: 'warning',
  equity: 'default',
  revenue: 'success',
  expense: 'destructive',
}

const columns = computed(() => {
  const cols = [
    { key: 'code', label: t('code') },
    { key: 'name', label: t('name') },
    { key: 'type', label: t('type') },
    { key: 'is_active', label: t('active'), format: v => v ? t('yes') : t('no') },
  ]
  if (props.can?.edit || props.can?.delete) {
    cols.push({ key: 'actions', label: '', sortable: false })
  }
  return cols
})

// Form dialog state
const showForm = ref(false)
const editingAccount = ref(null)

function openCreate() {
  editingAccount.value = null
  showForm.value = true
}

function openEdit(account) {
  editingAccount.value = account
  showForm.value = true
}

// Confirm delete state
const showDelete = ref(false)
const deletingAccount = ref(null)
const deleteProcessing = ref(false)

function confirmDelete(account) {
  deletingAccount.value = account
  showDelete.value = true
}

function performDelete() {
  if (!deletingAccount.value) return
  deleteProcessing.value = true
  router.delete(`/accounting/accounts/${deletingAccount.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      deleteProcessing.value = false
      showDelete.value = false
      deletingAccount.value = null
    },
  })
}

// Import dialog
const showImport = ref(false)
</script>

<template>
  <AppLayout :title="t('chart_of_accounts')" help-page="accounting-basics">
    <HelpText :title="t('help_chart_title')" class="mb-6">
      <p>{{ t('help_chart_text') }}</p>
    </HelpText>

    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>{{ t('chart_of_accounts') }}</CardTitle>
          <div class="flex gap-2">
            <Button v-if="can?.create" variant="outline" size="sm" @click="showImport = true">
              <Upload class="mr-1 h-4 w-4" /> {{ t('import') }}
            </Button>
            <a href="/accounting/accounts/export?format=csv" class="inline-flex items-center">
              <Button variant="outline" size="sm" type="button">
                <Download class="mr-1 h-4 w-4" /> {{ t('export') }}
              </Button>
            </a>
            <Button v-if="can?.create" size="sm" @click="openCreate">
              <Plus class="mr-1 h-4 w-4" /> {{ t('add_account') }}
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="accounts">
          <template #cell-type="{ value }">
            <Badge :variant="typeVariant[value] || 'default'">{{ value }}</Badge>
          </template>
          <template v-if="can?.edit || can?.delete" #cell-actions="{ row }">
            <div class="flex items-center gap-1 justify-end">
              <Button v-if="can?.edit" variant="ghost" size="icon" @click="openEdit(row)">
                <Pencil class="h-4 w-4" />
              </Button>
              <Button
                v-if="can?.delete && !row.has_transactions"
                variant="ghost"
                size="icon"
                @click="confirmDelete(row)"
              >
                <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <AccountFormDialog
      :open="showForm"
      :account="editingAccount"
      :account-types="accountTypes"
      :accounts="accounts"
      @close="showForm = false"
    />

    <AccountImportDialog
      :open="showImport"
      @close="showImport = false"
    />

    <ConfirmDialog
      :open="showDelete"
      :title="t('delete_account_confirm')"
      :message="deletingAccount ? `${deletingAccount.code} — ${deletingAccount.name}` : ''"
      :processing="deleteProcessing"
      @confirm="performDelete"
      @cancel="showDelete = false"
    />
  </AppLayout>
</template>
