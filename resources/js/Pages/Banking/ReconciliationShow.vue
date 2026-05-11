<script setup>
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import Modal from '@/Components/UI/Modal.vue'
import CsvColumnMappingModal from '@/Components/CsvColumnMappingModal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import Combobox from '@/Components/UI/Combobox.vue'
import FileUpload from '@/Components/UI/FileUpload.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { normalizeReconciliationShowContract } from '@/lib/inertiaContracts'
import { ref, computed, nextTick } from 'vue'
import { Upload, Check, Link2, RotateCcw, Loader2, UserX, Plus, AlertTriangle, Paperclip } from 'lucide-vue-next'

const props = defineProps({
  bankAccount: Object,
  transactions: Object,
  suggestions: { type: Object, default: () => ({}) },
  personalSuggestions: { type: Array, default: () => [] },
  filter: { type: String, default: 'unreconciled' },
  openInvoices: { type: Array, default: () => [] },
  openExpenses: { type: Array, default: () => [] },
  pageFeatures: { type: Object, default: () => ({}) },
})

const contract = computed(() => normalizeReconciliationShowContract(props))
const bankAccountSafe = computed(() => contract.value.bankAccount)
const transactionsSafe = computed(() => contract.value.transactions)
const suggestionsSafe = computed(() => contract.value.suggestions)
const personalSuggestionsSafe = computed(() => contract.value.personalSuggestions)
const filterSafe = computed(() => contract.value.filter)
const openInvoicesSafe = computed(() => contract.value.openInvoices)
const openExpensesSafe = computed(() => contract.value.openExpenses)
const pageFeaturesSafe = computed(() => contract.value.pageFeatures)

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

// Upload form
const showUploadModal = ref(false)
const uploadForm = useForm({ camt_file: null, csv_mapping: null, csv_delimiter: ',' })
const showCsvMapping = ref(false)
const csvHeaders = ref([])
const pendingCsvFile = ref(null)

function submitUpload() {
  uploadForm.post(`/reconciliation/${bankAccountSafe.value.uuid}/import`, {
    forceFormData: true,
    onSuccess: () => { showUploadModal.value = false; uploadForm.reset() },
  })
}

function onFileChange(file) {
  if (!file) return

  const ext = file.name.split('.').pop().toLowerCase()
  if (ext === 'csv') {
    // Read headers for column mapping
    pendingCsvFile.value = file
    const reader = new FileReader()
    reader.onload = (ev) => {
      const text = ev.target.result
      const firstLine = text.split('\n')[0]
      csvHeaders.value = firstLine.split(',').map(h => h.trim().replace(/^"|"$/g, ''))
      showCsvMapping.value = true
    }
    reader.readAsText(file)
  } else {
    uploadForm.camt_file = file
  }
}

function onCsvMappingConfirm({ mapping, delimiter }) {
  showCsvMapping.value = false
  uploadForm.camt_file = pendingCsvFile.value
  uploadForm.csv_mapping = mapping
  uploadForm.csv_delimiter = delimiter
  submitUpload()
}

// Match modals
const showMatchModal = ref(false)
const matchingTransaction = ref(null)
const matchType = ref('invoice') // 'invoice', 'expense', 'manual'

const matchInvoiceForm = useForm({ invoice_id: '' })
const matchExpenseForm = useForm({ expense_id: '', expense_account_code: '6530' })
const matchManualForm = useForm({ contra_account_code: '' })

// Invoice combobox options: "INV-001 — Customer Name (CHF 1'500.00) [payé]"
const invoiceOptions = computed(() =>
  openInvoicesSafe.value.map((inv) => ({
    value: inv.id,
    label: `${inv.number} — ${inv.customer?.name || '—'} (${formatCurrency(inv.total, inv.currency)})${inv.status === 'paid' ? ' ✓ ' + t('paid') : ''}`,
  }))
)

// Expense combobox options: "Description — Vendor (CHF 250.00)"
const expenseOptions = computed(() =>
  openExpensesSafe.value.map((exp) => ({
    value: exp.id,
    label: `${exp.description || exp.category || '—'} — ${exp.vendor || '—'} (${formatCurrency(exp.amount, exp.currency)})`,
  }))
)

function createInvoiceFromTransaction() {
  router.visit('/invoices/create')
}

function openMatchModal(transaction) {
  matchingTransaction.value = transaction
  matchType.value = transaction.type === 'credit' ? 'invoice' : 'expense'
  matchInvoiceForm.invoice_id = ''
  showMatchModal.value = true
}

function selectInvoiceSuggestion(inv) {
  matchInvoiceForm.invoice_id = inv.id
  nextTick(() => submitMatch())
}

function selectExpenseSuggestion(exp) {
  matchExpenseForm.expense_id = exp.id
  nextTick(() => submitMatch())
}

function submitMatch() {
  const txId = matchingTransaction.value.id
  if (matchType.value === 'invoice') {
    matchInvoiceForm.post(`/reconciliation/transactions/${txId}/invoice`, {
      onSuccess: () => closeMatchModal(),
    })
  } else if (matchType.value === 'expense') {
    matchExpenseForm.post(`/reconciliation/transactions/${txId}/expense`, {
      onSuccess: () => closeMatchModal(),
    })
  } else {
    matchManualForm.post(`/reconciliation/transactions/${txId}/manual`, {
      onSuccess: () => closeMatchModal(),
    })
  }
}

function quickMatch(transaction, type, id) {
  if (type === 'invoice') {
    router.post(`/reconciliation/transactions/${transaction.id}/invoice`, { invoice_id: id })
  } else if (type === 'expense') {
    router.post(`/reconciliation/transactions/${transaction.id}/expense`, {
      expense_id: id,
      expense_account_code: '6530',
    })
  }
}

const confirming = ref(null)

function confirmMatch(matchId) {
  if (confirming.value) return
  confirming.value = matchId
  router.post(`/reconciliation/matches/${matchId}/confirm`, {}, {
    onFinish: () => { confirming.value = null },
  })
}

function confidenceColor(score) {
  if (score >= 100) return 'green'
  if (score >= 90) return 'yellow'
  return 'orange'
}

function confidenceLabel(score) {
  if (score >= 100) return 'Exact QR'
  if (score >= 90) return 'High'
  return 'Possible'
}

function closeMatchModal() {
  showMatchModal.value = false
  matchingTransaction.value = null
  matchInvoiceForm.reset()
  matchExpenseForm.reset()
  matchManualForm.reset()
}

const autoReconciling = ref(false)

function autoReconcile() {
  if (autoReconciling.value) return
  autoReconciling.value = true
  router.post(`/reconciliation/${bankAccountSafe.value.uuid}/auto`, {}, {
    onFinish: () => { autoReconciling.value = false },
  })
}

// Personal transaction marking (mixed-use accounts)
const markingPersonal = ref(null)

function markAsPersonal(transactionId) {
  if (markingPersonal.value) return
  markingPersonal.value = transactionId
  router.post(`/reconciliation/transactions/${transactionId}/personal`, {}, {
    onFinish: () => { markingPersonal.value = null },
  })
}

// Bulk selection for personal marking
const selectedForPersonal = ref(new Set())
const bulkProcessing = ref(false)

function togglePersonalSelection(txId) {
  if (selectedForPersonal.value.has(txId)) {
    selectedForPersonal.value.delete(txId)
  } else {
    selectedForPersonal.value.add(txId)
  }
}

function bulkMarkAsPersonal() {
  if (bulkProcessing.value || selectedForPersonal.value.size === 0) return
  bulkProcessing.value = true
  router.post(`/reconciliation/${bankAccountSafe.value.uuid}/bulk-personal`, {
    transaction_ids: Array.from(selectedForPersonal.value),
  }, {
    onFinish: () => {
      bulkProcessing.value = false
      selectedForPersonal.value.clear()
    },
  })
}

// Triage summary for mixed-use accounts
const triageSummary = computed(() => {
  if (!bankAccountSafe.value.is_mixed_use || !transactionsSafe.value?.data) return null
  const all = transactionsSafe.value.data
  return {
    unclassified: all.filter(tx => !tx.is_reconciled).length,
    personal: all.filter(tx => tx.is_personal === true).length,
    business: all.filter(tx => tx.is_reconciled && tx.is_personal === false).length,
  }
})

function changeFilter(newFilter) {
  router.get(`/reconciliation/${bankAccountSafe.value.uuid}`, { filter: newFilter }, { preserveState: true })
}

const currentSuggestions = computed(() => {
  if (!matchingTransaction.value) return { invoices: [], expenses: [] }
  return suggestionsSafe.value[matchingTransaction.value.id] || { invoices: [], expenses: [] }
})

const justificationMissingCount = computed(() => {
  const txs = transactionsSafe.value?.data ?? []
  return txs.filter(
    tx => tx.is_reconciled
      && (
        (!tx.matched_invoice && !tx.matched_expense)
        || (tx.matched_expense && !tx.matched_expense.receipt_path)
      ),
  ).length
})
</script>

<template>
  <AppLayout :title="`${t('reconciliation')} — ${bankAccountSafe.name}`">
    <PageHeader>
      <template #start>
        <div class="flex items-center gap-3">
          <Button as="a" href="/reconciliation" variant="outline" size="sm">← {{ t('back') }}</Button>
          <h2 class="text-xl font-semibold">{{ bankAccountSafe.name }}</h2>
          <Badge v-if="bankAccountSafe.is_mixed_use" variant="outline">{{ t('mixed') }}</Badge>
          <Badge variant="secondary">{{ formatCurrency(bankAccountSafe.balance, bankAccountSafe.currency) }}</Badge>
        </div>
      </template>
      <div class="flex items-center gap-2">
        <Button v-if="pageFeaturesSafe.auto_reconciliation" @click="autoReconcile" variant="outline" :disabled="autoReconciling">
          <Loader2 v-if="autoReconciling" class="mr-2 h-4 w-4 animate-spin" />
          <RotateCcw v-else class="mr-2 h-4 w-4" />
          {{ t('auto_reconcile') }}
        </Button>
        <Button @click="showUploadModal = true">
          <Upload class="mr-2 h-4 w-4" /> {{ t('import_bank_statement') }}
        </Button>
      </div>
    </PageHeader>

    <!-- Filter tabs -->
    <div class="flex gap-2 mb-4">
      <Button
        v-for="f in ['unreconciled', 'reconciled', 'all']"
        :key="f"
        :variant="filterSafe === f ? 'default' : 'outline'"
        size="sm"
        @click="changeFilter(f)"
      >
        {{ t(f) || f.charAt(0).toUpperCase() + f.slice(1) }}
      </Button>
      <Badge
        v-if="justificationMissingCount > 0"
        variant="outline"
        class="ml-auto self-center border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
        :title="t('justification_missing_help')"
      >
        <AlertTriangle class="mr-1 h-3 w-3" />
        {{ t('no_receipt_count', { count: justificationMissingCount }) }}
      </Badge>
    </div>

    <!-- Triage summary for mixed-use accounts -->
    <div v-if="triageSummary" class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border bg-muted/30 px-4 py-2 text-sm">
      <span>{{ triageSummary.unclassified }} {{ t('unclassified') }}</span>
      <span class="text-muted-foreground">|</span>
      <span class="text-purple-600 dark:text-purple-400">{{ triageSummary.personal }} {{ t('personal') }}</span>
      <span class="text-muted-foreground">|</span>
      <span class="text-green-600 dark:text-green-400">{{ triageSummary.business }} {{ t('business') }}</span>
      <div v-if="selectedForPersonal.size > 0" class="ml-auto">
        <Button size="sm" variant="outline" :disabled="bulkProcessing" @click="bulkMarkAsPersonal">
          <UserX class="mr-1 h-3 w-3" />
          {{ t('mark_personal') }} ({{ selectedForPersonal.size }})
        </Button>
      </div>
    </div>

    <!-- Transactions list -->
    <Card>
      <CardHeader>
        <CardTitle>{{ t('transactions') }}</CardTitle>
        <CardDescription>
          {{ (transactionsSafe?.data?.length || 0) }} {{ t('transactions') }}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <EmptyState v-if="!transactionsSafe?.data?.length" :title="t('no_transactions')" />

        <div v-else class="space-y-3">
          <div
            v-for="tx in transactionsSafe.data"
            :key="tx.id"
            :class="[
              'flex items-start justify-between rounded-lg border p-4 transition-colors',
              tx.is_reconciled
                ? 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950'
                : 'border-[hsl(var(--border))] hover:border-[hsl(var(--primary))]',
            ]"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <Badge :variant="tx.type === 'credit' ? 'default' : 'secondary'">
                  {{ tx.type === 'credit' ? '↓ IN' : '↑ OUT' }}
                </Badge>
                <span class="text-sm text-muted-foreground">{{ formatDate(tx.date) }}</span>
                <Badge v-if="tx.is_reconciled" variant="outline" class="text-green-600">
                  <Check class="mr-1 h-3 w-3" /> {{ t('reconciled') }}
                </Badge>
                <Badge v-if="tx.is_personal" variant="outline" class="text-purple-600">
                  {{ t('personal') }}
                </Badge>
              </div>
              <p class="text-sm font-medium truncate">{{ tx.description || '—' }}</p>
              <div class="flex gap-3 mt-1 text-xs text-muted-foreground">
                <span v-if="tx.reference">Ref: {{ tx.reference }}</span>
                <span v-if="tx.structured_reference" class="font-mono">QR: {{ tx.structured_reference }}</span>
                <span v-if="tx.debtor_name">From: {{ tx.debtor_name }}</span>
                <span v-if="tx.creditor_name">To: {{ tx.creditor_name }}</span>
              </div>

              <!-- Show matched entity -->
              <div v-if="tx.matched_invoice" class="mt-2 text-xs flex items-center gap-1">
                <Badge variant="outline"><Link2 class="mr-1 h-3 w-3" />Invoice {{ tx.matched_invoice.number }}</Badge>
                <a
                  :href="`/invoices/${tx.matched_invoice.id}`"
                  class="text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--primary))]"
                  :title="t('view_document')"
                >
                  <Paperclip class="h-3 w-3" />
                </a>
              </div>
              <div v-if="tx.matched_expense" class="mt-2 text-xs flex items-center gap-1">
                <Badge variant="outline"><Link2 class="mr-1 h-3 w-3" />{{ tx.matched_expense.description }}</Badge>
                <a
                  v-if="tx.matched_expense.receipt_path"
                  :href="`/expenses/${tx.matched_expense.id}/receipt`"
                  class="text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--primary))]"
                  :title="t('view_receipt')"
                >
                  <Paperclip class="h-3 w-3" />
                </a>
                <Badge
                  v-else
                  variant="outline"
                  class="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                  :title="t('justification_missing_help')"
                >
                  <AlertTriangle class="mr-1 h-3 w-3" /> {{ t('justification_missing') }}
                </Badge>
              </div>
              <Badge
                v-if="tx.is_reconciled && !tx.matched_invoice && !tx.matched_expense"
                variant="outline"
                class="mt-2 border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                :title="t('justification_missing_help')"
              >
                <AlertTriangle class="mr-1 h-3 w-3" /> {{ t('justification_missing') }}
              </Badge>

              <!-- Quick suggestions for unreconciled -->
              <div v-if="!tx.is_reconciled && (suggestionsSafe[tx.id] || (bankAccountSafe.is_mixed_use && personalSuggestionsSafe.includes(tx.id)))" class="mt-2 flex flex-wrap gap-1">
                <!-- Personal suggestion badge -->
                <button
                  v-if="bankAccountSafe.is_mixed_use && personalSuggestionsSafe.includes(tx.id)"
                  class="inline-flex items-center gap-1 rounded-md border border-purple-300 bg-purple-50 px-2 py-0.5 text-xs text-purple-700 hover:bg-purple-100 dark:border-purple-800 dark:bg-purple-950 dark:text-purple-300"
                  :disabled="markingPersonal === tx.id"
                  @click="markAsPersonal(tx.id)"
                >
                  <UserX class="h-3 w-3" />
                  {{ t('personal') }}?
                </button>
                <template v-if="suggestionsSafe[tx.id].invoices?.length">
                  <button
                    v-for="inv in suggestionsSafe[tx.id].invoices.slice(0, 3)"
                    :key="inv.id"
                    :class="[
                      'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs transition-colors',
                      inv.match_score >= 100
                        ? 'border-green-300 bg-green-50 text-green-700 hover:bg-green-100 dark:border-green-800 dark:bg-green-950 dark:text-green-300'
                        : inv.match_score >= 90
                          ? 'border-yellow-300 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-300'
                          : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300',
                    ]"
                    :disabled="confirming === inv.match_id"
                    @click="inv.match_id ? confirmMatch(inv.match_id) : quickMatch(tx, 'invoice', inv.id)"
                  >
                    <Check class="h-3 w-3" />
                    <span class="font-medium">{{ confidenceLabel(inv.match_score) }}</span>
                    {{ inv.number }} ({{ formatCurrency(inv.total) }})
                    <span v-if="inv.customer || inv.client" class="opacity-70">— {{ (inv.customer ?? inv.client).name }}</span>
                  </button>
                </template>
                <template v-if="suggestionsSafe[tx.id].expenses?.length">
                  <button
                    v-for="exp in suggestionsSafe[tx.id].expenses.slice(0, 2)"
                    :key="exp.id"
                    class="inline-flex items-center gap-1 rounded-md border border-orange-200 bg-orange-50 px-2 py-0.5 text-xs text-orange-700 hover:bg-orange-100 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-300"
                    @click="quickMatch(tx, 'expense', exp.id)"
                  >
                    <Check class="h-3 w-3" />
                    {{ exp.description }} ({{ formatCurrency(exp.amount) }})
                  </button>
                </template>
              </div>
            </div>

            <div class="flex items-center gap-3 ml-4">
              <span :class="[
                'text-lg font-semibold tabular-nums',
                tx.type === 'credit' ? 'text-green-600' : 'text-red-600',
              ]">
                {{ tx.type === 'credit' ? '+' : '-' }}{{ formatCurrency(tx.amount) }}
              </span>
              <template v-if="!tx.is_reconciled && bankAccountSafe.is_mixed_use">
                <input
                  type="checkbox"
                  class="h-4 w-4 rounded border-[hsl(var(--input))]"
                  :checked="selectedForPersonal.has(tx.id)"
                  @change="togglePersonalSelection(tx.id)"
                />
                <Button
                  size="sm"
                  variant="outline"
                  class="text-purple-600 border-purple-300 hover:bg-purple-50 dark:text-purple-400 dark:border-purple-700 dark:hover:bg-purple-950"
                  :disabled="markingPersonal === tx.id"
                  @click="markAsPersonal(tx.id)"
                >
                  <UserX class="mr-1 h-3 w-3" />
                  {{ t('personal') }}
                </Button>
              </template>
              <Button
                v-if="!tx.is_reconciled"
                size="sm"
                variant="outline"
                @click="openMatchModal(tx)"
              >
                {{ t('match') }}
              </Button>
            </div>
          </div>

          <div v-if="transactionsSafe?.last_page > 1" class="mt-2 flex items-center justify-between border-t border-[hsl(var(--border))] pt-3">
            <span class="text-sm text-muted-foreground">
              {{ t('page') }} {{ transactionsSafe.current_page }} / {{ transactionsSafe.last_page }}
            </span>
            <div class="flex gap-2">
              <Button
                v-if="transactionsSafe.prev_page_url"
                size="sm"
                variant="outline"
                @click="router.visit(transactionsSafe.prev_page_url, { preserveState: true, preserveScroll: true })"
              >
                {{ t('previous') }}
              </Button>
              <Button
                v-if="transactionsSafe.next_page_url"
                size="sm"
                variant="outline"
                @click="router.visit(transactionsSafe.next_page_url, { preserveState: true, preserveScroll: true })"
              >
                {{ t('next') }}
              </Button>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Upload Modal -->
    <Modal :show="showUploadModal" @close="showUploadModal = false" :title="t('import_bank_statement')">
      <form class="space-y-6" @submit.prevent="submitUpload">
        <p class="text-sm text-muted-foreground">
          {{ t('import_bank_desc') }}
        </p>
        <FileUpload
          accept=".xml,.XML,.csv,.CSV,.sta,.mt940,.mt9"
          :label="t('file')"
          :error="uploadForm.errors.camt_file"
          @change="onFileChange"
        />
        <div class="flex justify-end gap-3">
          <Button variant="outline" type="button" @click="showUploadModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="uploadForm.processing || !uploadForm.camt_file">
            <Upload class="mr-2 h-4 w-4" /> {{ t('import') }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- CSV Column Mapping Modal -->
    <CsvColumnMappingModal
      :open="showCsvMapping"
      :headers="csvHeaders"
      @close="showCsvMapping = false"
      @confirm="onCsvMappingConfirm"
    />

    <!-- Match Modal -->
    <Modal :show="showMatchModal" @close="closeMatchModal" :title="t('reconcile_transaction')">
      <div v-if="matchingTransaction" class="space-y-4">
        <!-- Transaction summary -->
        <div class="rounded-lg border p-3 bg-muted/50">
          <div class="flex justify-between items-center">
            <div>
              <p class="text-sm font-medium">{{ matchingTransaction.description || '—' }}</p>
              <p class="text-xs text-muted-foreground">{{ formatDate(matchingTransaction.date) }}</p>
            </div>
            <span :class="[
              'text-lg font-semibold',
              matchingTransaction.type === 'credit' ? 'text-green-600' : 'text-red-600',
            ]">
              {{ matchingTransaction.type === 'credit' ? '+' : '-' }}{{ formatCurrency(matchingTransaction.amount) }}
            </span>
          </div>
        </div>

        <!-- Match type tabs -->
        <div class="flex gap-2">
          <Button
            v-for="mt in ['invoice', 'expense', 'manual']"
            :key="mt"
            :variant="matchType === mt ? 'default' : 'outline'"
            size="sm"
            @click="matchType = mt"
          >
            {{ mt === 'invoice' ? (t('invoice')) : mt === 'expense' ? (t('expense')) : (t('manual')) }}
          </Button>
        </div>

        <!-- Invoice match -->
        <form v-if="matchType === 'invoice'" class="space-y-3" @submit.prevent="submitMatch">
          <div v-if="currentSuggestions.invoices?.length" class="space-y-2">
            <p class="text-sm font-medium">{{ t('suggested_matches') }}</p>
            <button
              v-for="inv in currentSuggestions.invoices"
              :key="inv.id"
              type="button"
              class="w-full text-left rounded-md border p-2 text-sm hover:bg-muted/50 transition-colors"
              :class="matchInvoiceForm.invoice_id === inv.id ? 'border-[hsl(var(--primary))] bg-[hsl(var(--primary))]/10' : ''"
              :disabled="matchInvoiceForm.processing"
              @click="selectInvoiceSuggestion(inv)"
            >
              <div class="flex justify-between">
                <span class="font-medium">{{ inv.number }}</span>
                <span>{{ formatCurrency(inv.total) }}</span>
              </div>
              <span v-if="inv.customer || inv.client" class="text-xs text-muted-foreground">{{ (inv.customer ?? inv.client).name }}</span>
            </button>
          </div>
          <div class="space-y-1">
            <label class="text-sm font-medium">{{ t('invoice') }}</label>
            <Combobox
              v-model="matchInvoiceForm.invoice_id"
              :options="invoiceOptions"
              :placeholder="t('search_invoice')"
              :emptyText="t('no_invoices_found')"
              :error="matchInvoiceForm.errors.invoice_id"
            />
            <p v-if="matchInvoiceForm.errors.invoice_id" class="text-xs text-[hsl(var(--destructive))]">{{ matchInvoiceForm.errors.invoice_id }}</p>
          </div>
          <div class="flex justify-between gap-3">
            <Button variant="ghost" size="sm" type="button" @click="createInvoiceFromTransaction">
              <Plus class="mr-1 h-4 w-4" /> {{ t('create_invoice') }}
            </Button>
            <div class="flex gap-3">
              <Button variant="outline" type="button" @click="closeMatchModal">{{ t('cancel') }}</Button>
              <Button type="submit" :disabled="matchInvoiceForm.processing || !matchInvoiceForm.invoice_id">
                <Check class="mr-2 h-4 w-4" /> {{ t('reconcile') }}
              </Button>
            </div>
          </div>
        </form>

        <!-- Expense match -->
        <form v-if="matchType === 'expense'" class="space-y-3" @submit.prevent="submitMatch">
          <div v-if="currentSuggestions.expenses?.length" class="space-y-2">
            <p class="text-sm font-medium">{{ t('suggested_matches') }}</p>
            <button
              v-for="exp in currentSuggestions.expenses"
              :key="exp.id"
              type="button"
              class="w-full text-left rounded-md border p-2 text-sm hover:bg-muted/50 transition-colors"
              :class="matchExpenseForm.expense_id === exp.id ? 'border-[hsl(var(--primary))] bg-[hsl(var(--primary))]/10' : ''"
              :disabled="matchExpenseForm.processing"
              @click="selectExpenseSuggestion(exp)"
            >
              <div class="flex justify-between">
                <span class="font-medium">{{ exp.description }}</span>
                <span>{{ formatCurrency(exp.amount) }}</span>
              </div>
              <span class="text-xs text-muted-foreground">{{ exp.vendor }} — {{ exp.category }}</span>
            </button>
          </div>
          <details v-if="currentSuggestions.expenses?.length" class="text-sm">
            <summary class="cursor-pointer text-muted-foreground hover:text-foreground">{{ t('manual_entry') }}</summary>
            <div class="mt-2 space-y-3">
              <div class="space-y-1">
                <label class="text-sm font-medium">{{ t('expense') }}</label>
                <Combobox
                  v-model="matchExpenseForm.expense_id"
                  :options="expenseOptions"
                  :placeholder="t('search_expense')"
                  :emptyText="t('no_expenses_found')"
                  :error="matchExpenseForm.errors.expense_id"
                />
                <p v-if="matchExpenseForm.errors.expense_id" class="text-xs text-[hsl(var(--destructive))]">{{ matchExpenseForm.errors.expense_id }}</p>
              </div>
              <FormInput
                id="expense_account_code"
                v-model="matchExpenseForm.expense_account_code"
                :label="t('expense_account')"
                :error="matchExpenseForm.errors.expense_account_code"
                placeholder="6530"
              />
            </div>
          </details>
          <template v-else>
            <div class="space-y-1">
              <label class="text-sm font-medium">{{ t('expense') }}</label>
              <Combobox
                v-model="matchExpenseForm.expense_id"
                :options="expenseOptions"
                :placeholder="t('search_expense')"
                :emptyText="t('no_expenses_found')"
                :error="matchExpenseForm.errors.expense_id"
              />
              <p v-if="matchExpenseForm.errors.expense_id" class="text-xs text-[hsl(var(--destructive))]">{{ matchExpenseForm.errors.expense_id }}</p>
            </div>
            <FormInput
              id="expense_account_code"
              v-model="matchExpenseForm.expense_account_code"
              :label="t('expense_account')"
              :error="matchExpenseForm.errors.expense_account_code"
              placeholder="6530"
            />
          </template>
          <div class="flex justify-end gap-3">
            <Button variant="outline" type="button" @click="closeMatchModal">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="matchExpenseForm.processing || !matchExpenseForm.expense_id">
              <Check class="mr-2 h-4 w-4" /> {{ t('reconcile') }}
            </Button>
          </div>
        </form>

        <!-- Manual match -->
        <form v-if="matchType === 'manual'" class="space-y-3" @submit.prevent="submitMatch">
          <p class="text-sm text-muted-foreground">
            {{ t('manual_reconcile_help') }}
          </p>
          <FormInput
            id="contra_account_code"
            v-model="matchManualForm.contra_account_code"
            :label="t('contra_account')"
            :error="matchManualForm.errors.contra_account_code"
            placeholder="3000"
            required
          />
          <div class="flex justify-end gap-3">
            <Button variant="outline" type="button" @click="closeMatchModal">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="matchManualForm.processing || !matchManualForm.contra_account_code">
              <Check class="mr-2 h-4 w-4" /> {{ t('reconcile') }}
            </Button>
          </div>
        </form>
      </div>
    </Modal>
  </AppLayout>
</template>
