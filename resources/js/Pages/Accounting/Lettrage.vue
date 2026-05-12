<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { buildAccountOptions } from '@/lib/accountOptions'
import { useDocsUrl } from '@/lib/useDocsUrl'
import { Link2, Unlink, CalendarDays } from 'lucide-vue-next'

const { t } = useTranslations()
const { formatDate, formatMoney } = useFormatters()
const { url: docsUrl } = useDocsUrl()
const chartHelpHref = docsUrl('chart-of-accounts')

const props = defineProps({
  accounts: Array,
  account: Object,
  openItems: Array,
  lots: Object,
  filterDate: String,
})

// Account selection
const selectedAccountId = ref(props.account?.id ? String(props.account.id) : '')
const dateFilter = ref(props.filterDate ?? '')

const accountOptions = computed(() =>
  buildAccountOptions(props.accounts ?? []).map(o => ({ ...o, value: String(o.value) })),
)

function navigateToAccount() {
  if (!selectedAccountId.value) return
  const params = { account: selectedAccountId.value }
  if (dateFilter.value) params.date = dateFilter.value
  router.get('/accounting/account-matching', params, { preserveState: false })
}

watch(selectedAccountId, () => navigateToAccount())

function applyDateFilter() {
  navigateToAccount()
}

// Multi-select for lettering
const selectedLineIds = ref([])

function toggleLine(id) {
  const idx = selectedLineIds.value.indexOf(id)
  if (idx === -1) {
    selectedLineIds.value.push(id)
  } else {
    selectedLineIds.value.splice(idx, 1)
  }
}

function isLineSelected(id) {
  return selectedLineIds.value.includes(id)
}

const selectedBalance = computed(() => {
  if (!props.openItems) return { debit: 0, credit: 0 }
  const selected = props.openItems.filter(i => selectedLineIds.value.includes(i.id))
  return {
    debit: selected.reduce((s, l) => s + parseFloat(l.debit || 0), 0),
    credit: selected.reduce((s, l) => s + parseFloat(l.credit || 0), 0),
  }
})

const isBalanced = computed(() => {
  const d = selectedBalance.value.debit.toFixed(2)
  const c = selectedBalance.value.credit.toFixed(2)
  return d === c && selectedLineIds.value.length >= 2
})

const lettering = ref(false)

function letterSelected() {
  if (!isBalanced.value || !props.account) return
  lettering.value = true
  router.post('/accounting/account-matching', {
    account_id: props.account.id,
    line_ids: selectedLineIds.value,
  }, {
    preserveScroll: true,
    onFinish: () => {
      lettering.value = false
      selectedLineIds.value = []
    },
  })
}

// Confirm unletter
const unletterTarget = ref(null)
const unlettering = ref(false)

function confirmUnletter(lot) {
  unletterTarget.value = lot
}

function executeUnletter() {
  if (!unletterTarget.value) return
  unlettering.value = true
  router.delete(`/accounting/account-matching/${unletterTarget.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      unlettering.value = false
      unletterTarget.value = null
    },
  })
}

const openItemColumns = computed(() => [
  { key: 'select', label: '', sortable: false, class: 'w-10' },
  { key: 'date', label: t('date'), format: (_, row) => formatDate(row.journal_entry?.date) },
  { key: 'reference', label: t('reference'), format: (_, row) => row.journal_entry?.reference ?? '' },
  { key: 'description', label: t('description') },
  { key: 'debit', label: t('debit'), format: v => v > 0 ? formatMoney(v) : '' },
  { key: 'credit', label: t('credit'), format: v => v > 0 ? formatMoney(v) : '' },
])

const lotColumns = computed(() => [
  { key: 'letter_key', label: t('lettrage_key') },
  { key: 'line_ids', label: t('lines'), format: v => `${v.length} ${t('lines').toLowerCase()}` },
  { key: 'lettered_at', label: t('lettrage_lettered_at'), format: v => formatDate(v) },
  { key: 'actions', label: '', sortable: false, class: 'w-20' },
])
</script>

<template>
  <AppLayout :title="t('lettrage')">
    <HelpText :title="t('lettrage_index')" class="mb-6">
      <p>{{ t('help_lettrage_text', 'Select an account and match open debit/credit lines that offset each other.') }}</p>
    </HelpText>

    <!-- Account selector -->
    <Card class="mb-6">
      <CardContent class="pt-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
          <div class="flex-1">
            <SearchableSelect
              id="account"
              v-model="selectedAccountId"
              :label="t('lettrage_account')"
              :options="accountOptions"
              group-key="group"
              :placeholder="t('select')"
              :help-href="chartHelpHref"
              :help-label="t('chart_of_accounts')"
            />
          </div>
          <div class="sm:w-48">
            <label class="mb-1.5 block text-sm font-medium" for="date_filter">
              {{ t('date') }}
            </label>
            <input
              id="date_filter"
              v-model="dateFilter"
              type="date"
              class="flex h-9 w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-1 text-sm shadow-sm"
              @change="applyDateFilter"
            >
          </div>
        </div>
      </CardContent>
    </Card>

    <template v-if="account">
      <!-- Open Items table -->
      <Card class="mb-6">
        <CardHeader>
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <CardTitle>{{ t('lettrage_open_items') }} — {{ account.code }} {{ account.name }}</CardTitle>
            <Button
              size="sm"
              :disabled="!isBalanced || lettering"
              @click="letterSelected"
            >
              <Link2 class="mr-1 h-4 w-4" />
              {{ t('lettrage_letter_lines') }}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="selectedLineIds.length >= 2" class="mb-4 flex flex-wrap items-center gap-3 rounded-md bg-[hsl(var(--muted))] px-4 py-2 text-sm">
            <span>{{ t('debit') }}: {{ formatMoney(selectedBalance.debit) }}</span>
            <span>{{ t('credit') }}: {{ formatMoney(selectedBalance.credit) }}</span>
            <Badge :variant="isBalanced ? 'success' : 'destructive'">
              {{ isBalanced ? t('balanced') : t('unbalanced') }}
            </Badge>
          </div>

          <DataTable
            :columns="openItemColumns"
            :rows="openItems"
            :empty-message="t('no_open_items', 'No open items for this account.')"
          >
            <template #cell-select="{ row }">
              <input
                type="checkbox"
                :checked="isLineSelected(row.id)"
                class="h-4 w-4 rounded border-[hsl(var(--input))]"
                @change="toggleLine(row.id)"
              >
            </template>
          </DataTable>
        </CardContent>
      </Card>

      <!-- Existing lots -->
      <Card v-if="lots && (lots?.data ?? []).length > 0">
        <CardHeader>
          <CardTitle>{{ t('lettrage') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable :columns="lotColumns" :rows="lots?.data ?? []" :pagination="lots">
            <template #cell-actions="{ row }">
              <Button
                variant="ghost"
                size="icon"
                :title="t('lettrage_unletter')"
                @click="confirmUnletter(row)"
              >
                <Unlink class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </Button>
            </template>
          </DataTable>
        </CardContent>
      </Card>
    </template>

    <template v-else>
      <Card>
        <CardContent class="py-12 text-center text-sm text-[hsl(var(--muted-foreground))]">
          {{ t('select_account_to_start', 'Select an account above to view open items.') }}
        </CardContent>
      </Card>
    </template>

    <ConfirmDialog
      :open="!!unletterTarget"
      :title="t('lettrage_unletter')"
      :message="unletterTarget ? `${t('lettrage_key')}: ${unletterTarget.letter_key}` : ''"
      :processing="unlettering"
      @confirm="executeUnletter"
      @cancel="unletterTarget = null"
    />
  </AppLayout>
</template>
