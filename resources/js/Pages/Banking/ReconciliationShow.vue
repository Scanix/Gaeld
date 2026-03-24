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
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'
import { Upload, Check, Link2, RotateCcw } from 'lucide-vue-next'

const props = defineProps({
  bankAccount: Object,
  transactions: Object,
  suggestions: { type: Object, default: () => ({}) },
  filter: { type: String, default: 'unreconciled' },
  pageFeatures: { type: Object, default: () => ({}) },
})

const { t } = useTranslations()

// Upload form
const showUploadModal = ref(false)
const uploadForm = useForm({ camt_file: null })

function submitUpload() {
  uploadForm.post(`/reconciliation/${props.bankAccount.id}/import`, {
    forceFormData: true,
    onSuccess: () => { showUploadModal.value = false; uploadForm.reset() },
  })
}

function onFileChange(e) {
  uploadForm.camt_file = e.target.files[0]
}

// Match modals
const showMatchModal = ref(false)
const matchingTransaction = ref(null)
const matchType = ref('invoice') // 'invoice', 'expense', 'manual'

const matchInvoiceForm = useForm({ invoice_id: '' })
const matchExpenseForm = useForm({ expense_id: '', expense_account_code: '6530' })
const matchManualForm = useForm({ contra_account_code: '' })

function openMatchModal(transaction) {
  matchingTransaction.value = transaction
  matchType.value = transaction.type === 'credit' ? 'invoice' : 'expense'
  showMatchModal.value = true
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

function confirmMatch(matchId) {
  router.post(`/reconciliation/matches/${matchId}/confirm`)
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

function autoReconcile() {
  router.post(`/reconciliation/${props.bankAccount.id}/auto`)
}

function changeFilter(newFilter) {
  router.get(`/reconciliation/${props.bankAccount.id}`, { filter: newFilter }, { preserveState: true })
}

const currentSuggestions = computed(() => {
  if (!matchingTransaction.value) return { invoices: [], expenses: [] }
  return props.suggestions[matchingTransaction.value.id] || { invoices: [], expenses: [] }
})
</script>

<template>
  <AppLayout :title="`${t('reconciliation') || 'Reconciliation'} — ${bankAccount.name}`">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <Button as="a" href="/reconciliation" variant="outline" size="sm">← {{ t('back') || 'Back' }}</Button>
        <h2 class="text-xl font-semibold">{{ bankAccount.name }}</h2>
        <Badge variant="secondary">{{ formatCurrency(bankAccount.balance, bankAccount.currency) }}</Badge>
      </div>
      <div class="flex items-center gap-2">
        <Button v-if="pageFeatures.auto_reconciliation" @click="autoReconcile" variant="outline">
          <RotateCcw class="mr-2 h-4 w-4" /> {{ t('auto_reconcile') || 'Auto Reconcile' }}
        </Button>
        <Button @click="showUploadModal = true">
          <Upload class="mr-2 h-4 w-4" /> {{ t('import_camt') || 'Import CAMT' }}
        </Button>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="flex gap-2 mb-4">
      <Button
        v-for="f in ['unreconciled', 'reconciled', 'all']"
        :key="f"
        :variant="filter === f ? 'default' : 'outline'"
        size="sm"
        @click="changeFilter(f)"
      >
        {{ t(f) || f.charAt(0).toUpperCase() + f.slice(1) }}
      </Button>
    </div>

    <!-- Transactions list -->
    <Card>
      <CardHeader>
        <CardTitle>{{ t('transactions') || 'Transactions' }}</CardTitle>
        <CardDescription>
          {{ (transactions?.data?.length || 0) }} {{ t('transactions') || 'transactions' }}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div v-if="!transactions?.data?.length" class="py-8 text-center text-muted-foreground">
          {{ t('no_transactions') || 'No transactions found.' }}
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="tx in transactions.data"
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
                  <Check class="mr-1 h-3 w-3" /> {{ t('reconciled') || 'Reconciled' }}
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
              <div v-if="tx.matched_invoice" class="mt-2 text-xs">
                <Badge variant="outline"><Link2 class="mr-1 h-3 w-3" />Invoice {{ tx.matched_invoice.number }}</Badge>
              </div>
              <div v-if="tx.matched_expense" class="mt-2 text-xs">
                <Badge variant="outline"><Link2 class="mr-1 h-3 w-3" />{{ tx.matched_expense.description }}</Badge>
              </div>

              <!-- Quick suggestions for unreconciled -->
              <div v-if="!tx.is_reconciled && suggestions[tx.id]" class="mt-2 flex flex-wrap gap-1">
                <template v-if="suggestions[tx.id].invoices?.length">
                  <button
                    v-for="inv in suggestions[tx.id].invoices.slice(0, 3)"
                    :key="inv.id"
                    :class="[
                      'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs transition-colors',
                      inv.match_score >= 100
                        ? 'border-green-300 bg-green-50 text-green-700 hover:bg-green-100 dark:border-green-800 dark:bg-green-950 dark:text-green-300'
                        : inv.match_score >= 90
                          ? 'border-yellow-300 bg-yellow-50 text-yellow-700 hover:bg-yellow-100 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-300'
                          : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300',
                    ]"
                    @click="inv.match_id ? confirmMatch(inv.match_id) : quickMatch(tx, 'invoice', inv.id)"
                  >
                    <Check class="h-3 w-3" />
                    <span class="font-medium">{{ confidenceLabel(inv.match_score) }}</span>
                    {{ inv.number }} ({{ formatCurrency(inv.total) }})
                    <span v-if="inv.customer || inv.client" class="opacity-70">— {{ (inv.customer ?? inv.client).name }}</span>
                  </button>
                </template>
                <template v-if="suggestions[tx.id].expenses?.length">
                  <button
                    v-for="exp in suggestions[tx.id].expenses.slice(0, 2)"
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
              <Button
                v-if="!tx.is_reconciled"
                size="sm"
                variant="outline"
                @click="openMatchModal(tx)"
              >
                {{ t('match') || 'Match' }}
              </Button>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Upload CAMT Modal -->
    <Modal :show="showUploadModal" @close="showUploadModal = false" :title="t('import_camt') || 'Import CAMT File'">
      <form class="space-y-4" @submit.prevent="submitUpload">
        <p class="text-sm text-muted-foreground">
          Upload a CAMT.053 (statement) or CAMT.054 (notification) file.
        </p>
        <div class="space-y-2">
          <label class="text-sm font-medium">{{ t('file') || 'File' }} <span class="text-[hsl(var(--destructive))]">*</span></label>
          <input
            type="file"
            accept=".xml,.XML"
            class="flex h-9 w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium"
            @change="onFileChange"
          />
          <p v-if="uploadForm.errors.camt_file" class="text-xs text-[hsl(var(--destructive))]">{{ uploadForm.errors.camt_file }}</p>
        </div>
        <div class="flex justify-end gap-3">
          <Button variant="outline" type="button" @click="showUploadModal = false">{{ t('cancel') || 'Cancel' }}</Button>
          <Button type="submit" :disabled="uploadForm.processing || !uploadForm.camt_file">
            <Upload class="mr-2 h-4 w-4" /> {{ t('import') || 'Import' }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Match Modal -->
    <Modal :show="showMatchModal" @close="closeMatchModal" :title="t('reconcile_transaction') || 'Reconcile Transaction'">
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
            {{ mt === 'invoice' ? (t('invoice') || 'Invoice') : mt === 'expense' ? (t('expense') || 'Expense') : (t('manual') || 'Manual') }}
          </Button>
        </div>

        <!-- Invoice match -->
        <form v-if="matchType === 'invoice'" class="space-y-3" @submit.prevent="submitMatch">
          <div v-if="currentSuggestions.invoices?.length" class="space-y-2">
            <p class="text-sm font-medium">{{ t('suggested_matches') || 'Suggested Matches' }}</p>
            <button
              v-for="inv in currentSuggestions.invoices"
              :key="inv.id"
              type="button"
              class="w-full text-left rounded-md border p-2 text-sm hover:bg-muted/50 transition-colors"
              :class="matchInvoiceForm.invoice_id === inv.id ? 'border-[hsl(var(--primary))] bg-muted/50' : ''"
              @click="matchInvoiceForm.invoice_id = inv.id"
            >
              <div class="flex justify-between">
                <span class="font-medium">{{ inv.number }}</span>
                <span>{{ formatCurrency(inv.total) }}</span>
              </div>
              <span v-if="inv.customer || inv.client" class="text-xs text-muted-foreground">{{ (inv.customer ?? inv.client).name }}</span>
            </button>
          </div>
          <FormInput
            id="invoice_id"
            v-model="matchInvoiceForm.invoice_id"
            :label="t('invoice_id') || 'Invoice ID'"
            :error="matchInvoiceForm.errors.invoice_id"
            :placeholder="t('enter_invoice_id') || 'Select or enter invoice ID'"
          />
          <div class="flex justify-end gap-3">
            <Button variant="outline" type="button" @click="closeMatchModal">{{ t('cancel') || 'Cancel' }}</Button>
            <Button type="submit" :disabled="matchInvoiceForm.processing || !matchInvoiceForm.invoice_id">
              <Check class="mr-2 h-4 w-4" /> {{ t('reconcile') || 'Reconcile' }}
            </Button>
          </div>
        </form>

        <!-- Expense match -->
        <form v-if="matchType === 'expense'" class="space-y-3" @submit.prevent="submitMatch">
          <div v-if="currentSuggestions.expenses?.length" class="space-y-2">
            <p class="text-sm font-medium">{{ t('suggested_matches') || 'Suggested Matches' }}</p>
            <button
              v-for="exp in currentSuggestions.expenses"
              :key="exp.id"
              type="button"
              class="w-full text-left rounded-md border p-2 text-sm hover:bg-muted/50 transition-colors"
              :class="matchExpenseForm.expense_id === exp.id ? 'border-[hsl(var(--primary))] bg-muted/50' : ''"
              @click="matchExpenseForm.expense_id = exp.id"
            >
              <div class="flex justify-between">
                <span class="font-medium">{{ exp.description }}</span>
                <span>{{ formatCurrency(exp.amount) }}</span>
              </div>
              <span class="text-xs text-muted-foreground">{{ exp.vendor }} — {{ exp.category }}</span>
            </button>
          </div>
          <FormInput
            id="expense_id"
            v-model="matchExpenseForm.expense_id"
            :label="t('expense_id') || 'Expense ID'"
            :error="matchExpenseForm.errors.expense_id"
            :placeholder="t('enter_expense_id') || 'Select or enter expense ID'"
          />
          <FormInput
            id="expense_account_code"
            v-model="matchExpenseForm.expense_account_code"
            :label="t('expense_account') || 'Expense Account Code'"
            :error="matchExpenseForm.errors.expense_account_code"
            placeholder="6530"
          />
          <div class="flex justify-end gap-3">
            <Button variant="outline" type="button" @click="closeMatchModal">{{ t('cancel') || 'Cancel' }}</Button>
            <Button type="submit" :disabled="matchExpenseForm.processing || !matchExpenseForm.expense_id">
              <Check class="mr-2 h-4 w-4" /> {{ t('reconcile') || 'Reconcile' }}
            </Button>
          </div>
        </form>

        <!-- Manual match -->
        <form v-if="matchType === 'manual'" class="space-y-3" @submit.prevent="submitMatch">
          <p class="text-sm text-muted-foreground">
            {{ t('manual_reconcile_help') || 'Enter the contra account code to post this transaction to the ledger.' }}
          </p>
          <FormInput
            id="contra_account_code"
            v-model="matchManualForm.contra_account_code"
            :label="t('contra_account') || 'Contra Account Code'"
            :error="matchManualForm.errors.contra_account_code"
            placeholder="3000"
            required
          />
          <div class="flex justify-end gap-3">
            <Button variant="outline" type="button" @click="closeMatchModal">{{ t('cancel') || 'Cancel' }}</Button>
            <Button type="submit" :disabled="matchManualForm.processing || !matchManualForm.contra_account_code">
              <Check class="mr-2 h-4 w-4" /> {{ t('reconcile') || 'Reconcile' }}
            </Button>
          </div>
        </form>
      </div>
    </Modal>
  </AppLayout>
</template>
