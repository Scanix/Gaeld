<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import AccountFormDialog from '@/Components/AccountFormDialog.vue'
import AccountImportDialog from '@/Components/AccountImportDialog.vue'
import { useTranslations } from '@/lib/useTranslations'
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import HelpText from '@/Components/HelpText.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { Plus, Upload, Download, Pencil, Trash2, BookOpen, Search } from 'lucide-vue-next'

const props = defineProps({
  accounts: Object,
  can: Object,
  accountTypes: Array,
  query: {
    type: Object,
    default: () => ({ search: '' }),
  },
})

const { t } = useTranslations()

const search = ref(props.query?.search ?? '')

function handleSearch() {
  router.get('/accounting/accounts', { search: search.value || undefined }, { preserveState: true, replace: true })
}

const groupLabels = {
  1: t('account_group_1'),
  2: t('account_group_2'),
  3: t('account_group_3'),
  4: t('account_group_4'),
  5: t('account_group_5'),
  6: t('account_group_6'),
  7: t('account_group_7'),
  8: t('account_group_8'),
  9: t('account_group_9'),
}

const filteredAccounts = computed(() => {
  return props.accounts?.data ?? []
})

const accountGroups = computed(() => {
  const map = {}
  for (const account of filteredAccounts.value) {
    const digit = String(account.code)?.[0] || '0'
    if (!map[digit]) map[digit] = { label: groupLabels[digit] || digit, accounts: [] }
    map[digit].accounts.push(account)
  }
  return Object.values(map)
})

const typeVariant = {
  asset: 'info',
  liability: 'warning',
  equity: 'default',
  revenue: 'success',
  expense: 'destructive',
}

const typeLabel = {
  asset: t('type_asset'),
  liability: t('type_liability'),
  equity: t('type_equity'),
  revenue: t('type_revenue'),
  expense: t('type_expense'),
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

function requestExport() {
  router.get('/accounting/accounts/export', { format: 'csv' }, { preserveScroll: true })
}
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
            <Button variant="outline" size="sm" type="button" @click="requestExport">
              <Download class="mr-1 h-4 w-4" /> {{ t('export') }}
            </Button>
            <Button v-if="can?.create" size="sm" @click="openCreate">
              <Plus class="mr-1 h-4 w-4" /> {{ t('add_account') }}
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <div class="mb-4">
          <div class="relative">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[hsl(var(--muted-foreground))]" />
            <FormInput
              id="search-accounts"
              v-model="search"
              :placeholder="t('search_accounts')"
              class="pl-9"
              @input="handleSearch"
            />
          </div>
        </div>
        <template v-if="filteredAccounts.length === 0">
          <EmptyState
            :icon="BookOpen"
            :title="t('empty_chart_of_accounts_title')"
            :description="t('empty_chart_of_accounts_desc')"
            :action-label="can?.create ? t('import_accounts') : ''"
            @action="showImport = true"
          />
        </template>
        <template v-else>
          <div v-for="group in accountGroups" :key="group.label" class="mb-6 last:mb-0">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-[hsl(var(--muted-foreground))]">{{ group.label }}</h3>
            <DataTable :columns="columns" :rows="group.accounts">
              <template #cell-type="{ value }">
                <Badge :variant="typeVariant[value] || 'default'">{{ typeLabel[value] || value }}</Badge>
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
          </div>

          <!-- Pagination -->
          <div v-if="accounts?.last_page > 1" class="mt-4 flex items-center justify-between border-t border-[hsl(var(--border))] pt-4">
            <span class="text-sm text-[hsl(var(--muted-foreground))]">
              {{ t('page') }} {{ accounts.current_page }} / {{ accounts.last_page }}
            </span>
            <div class="flex gap-2">
              <a v-if="accounts.prev_page_url" :href="accounts.prev_page_url">
                <Button variant="outline" size="sm">{{ t('previous') }}</Button>
              </a>
              <a v-if="accounts.next_page_url" :href="accounts.next_page_url">
                <Button variant="outline" size="sm">{{ t('next') }}</Button>
              </a>
            </div>
          </div>
        </template>
      </CardContent>
    </Card>

    <AccountFormDialog
      :open="showForm"
      :account="editingAccount"
      :account-types="accountTypes"
      :accounts="accounts?.data ?? []"
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
