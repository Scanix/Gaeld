<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { currencyOptions } from '@/lib/contactOptions'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'

const props = defineProps({
  bankAccount: Object,
  transactions: Object,
  accounts: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const showTransactionModal = ref(false)
const showEditModal = ref(false)
const showDeleteDialog = ref(false)
const deleting = ref(false)

const transactionForm = useForm({
  date: new Date().toISOString().slice(0, 10),
  description: '',
  amount: '',
  type: 'credit',
  reference: '',
  contra_account_code: '',
})

const editForm = useForm({
  name: props.bankAccount.name,
  iban: props.bankAccount.iban ?? '',
  bank_name: props.bankAccount.bank_name ?? '',
  currency: props.bankAccount.currency ?? 'CHF',
  account_id: props.bankAccount.account_id?.toString() ?? '',
  is_mixed_use: props.bankAccount.is_mixed_use ?? false,
})

function recordTransaction() {
  transactionForm.post(`/banking/${props.bankAccount.uuid}/transactions`, {
    onSuccess: () => {
      showTransactionModal.value = false
      transactionForm.reset()
    },
  })
}

function submitEdit() {
  editForm.put(`/banking/${props.bankAccount.uuid}`, {
    onSuccess: () => { showEditModal.value = false },
  })
}

function executeDelete() {
  deleting.value = true
  router.delete(`/banking/${props.bankAccount.uuid}`, {
    onFinish: () => { deleting.value = false },
  })
}

const typeOptions = [
  { value: 'credit', label: t('credit') },
  { value: 'debit', label: t('debit') },
]

const accountOptions = computed(() =>
  props.accounts.map(a => ({ value: a.code, label: `${a.code} — ${a.name}` }))
)

const accountIdOptions = computed(() =>
  props.accounts.map(a => ({ value: a.id.toString(), label: `${a.code} — ${a.name}` }))
)

const columns = computed(() => [
  { key: 'date', label: t('date'), format: (v) => formatDate(v) },
  { key: 'description', label: t('description') },
  { key: 'reference', label: t('reference'), format: (v) => v || '—' },
  { key: 'type', label: t('type') },
  { key: 'amount', label: t('amount'), class: 'text-right', format: (v) => formatCurrency(v) },
])
</script>

<template>
  <AppLayout :title="`${t('bank')} — ${bankAccount.name}`">
    <Breadcrumb :items="[{ label: t('bank_accounts'), href: '/banking' }, { label: bankAccount.name }]" class="mb-4" />

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div class="flex items-center gap-3">
        <h2 class="text-xl font-semibold">{{ bankAccount.name }}</h2>
        <Badge v-if="bankAccount.is_mixed_use" variant="outline">{{ t('mixed') }}</Badge>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button size="sm" variant="outline" @click="showEditModal = true">
          <Pencil class="h-4 w-4 sm:mr-1" />
          <span class="hidden sm:inline">{{ t('edit') }}</span>
        </Button>
        <Button size="sm" @click="showTransactionModal = true">
          <Plus class="h-4 w-4 sm:mr-1" />
          <span class="hidden sm:inline">{{ t('record_transaction') }}</span>
        </Button>
        <Button size="sm" variant="destructive" @click="showDeleteDialog = true">
          <Trash2 class="h-4 w-4 sm:mr-1" />
          <span class="hidden sm:inline">{{ t('delete') }}</span>
        </Button>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3 mb-6">
      <Card>
        <CardHeader><CardTitle class="text-sm">{{ t('balance') }}</CardTitle></CardHeader>
        <CardContent>
          <p class="text-2xl font-bold">{{ formatCurrency(bankAccount.balance, bankAccount.currency) }}</p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle class="text-sm">{{ t('iban') }}</CardTitle></CardHeader>
        <CardContent>
          <p class="text-sm font-mono">{{ bankAccount.iban || '—' }}</p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader><CardTitle class="text-sm">{{ t('bank') }}</CardTitle></CardHeader>
        <CardContent>
          <p class="text-sm">{{ bankAccount.bank_name || '—' }}</p>
          <p class="text-xs text-[hsl(var(--muted-foreground))]" v-if="bankAccount.ledger_account">
            {{ t('ledger') }}: {{ bankAccount.ledger_account.code }} — {{ bankAccount.ledger_account.name }}
          </p>
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader><CardTitle>{{ t('transactions') }}</CardTitle></CardHeader>
      <CardContent>
        <DataTable
          :columns="columns"
          :rows="transactions?.data ?? []"
          :pagination="transactions"
          :empty-message="t('no_transactions_recorded')"
        >
          <template #cell-type="{ value }">
            <Badge :variant="value === 'credit' ? 'default' : 'secondary'">
              {{ t('banking_type_' + value) }}
            </Badge>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <!-- Record Transaction Modal -->
    <Modal :open="showTransactionModal" :title="t('record_transaction')" @close="showTransactionModal = false">
      <form class="space-y-6" @submit.prevent="recordTransaction">
        <FormInput
          id="txn-date"
          v-model="transactionForm.date"
          type="date"
          :label="t('date')"
          :error="transactionForm.errors.date"
          required
        />
        <FormInput
          id="txn-description"
          v-model="transactionForm.description"
          :label="t('description')"
          :error="transactionForm.errors.description"
        />
        <FormInput
          id="txn-amount"
          v-model="transactionForm.amount"
          type="number"
          :label="t('amount')"
          :error="transactionForm.errors.amount"
          required
        />
        <FormSelect
          id="txn-type"
          v-model="transactionForm.type"
          :label="t('type')"
          :options="typeOptions"
          :error="transactionForm.errors.type"
          required
        />
        <FormSelect
          id="txn-contra-account"
          v-model="transactionForm.contra_account_code"
          :label="t('contra_account')"
          :options="accountOptions"
          :placeholder="t('select_account')"
          :error="transactionForm.errors.contra_account_code"
          required
        />
        <FormInput
          id="txn-reference"
          v-model="transactionForm.reference"
          :label="t('reference_optional')"
          :error="transactionForm.errors.reference"
        />
        <div class="flex justify-end gap-3">
          <Button type="button" variant="outline" @click="showTransactionModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="transactionForm.processing" :loading="transactionForm.processing">{{ t('record') }}</Button>
        </div>
      </form>
    </Modal>

    <!-- Edit Bank Account Modal -->
    <Modal :open="showEditModal" :title="t('edit_bank_account')" @close="showEditModal = false">
      <form class="space-y-6" @submit.prevent="submitEdit">
        <FormInput id="edit-name" v-model="editForm.name" :label="t('account_name')" :error="editForm.errors.name" required />
        <FormInput id="edit-iban" v-model="editForm.iban" :label="t('iban')" :error="editForm.errors.iban" />
        <FormInput id="edit-bank-name" v-model="editForm.bank_name" :label="t('bank_name')" :error="editForm.errors.bank_name" />
        <FormSelect
          id="edit-currency"
          v-model="editForm.currency"
          :label="t('currency')"
          :options="currencyOptions(t)"
          :error="editForm.errors.currency"
        />
        <FormSelect
          id="edit-account-id"
          v-model="editForm.account_id"
          :label="t('ledger_account')"
          :options="accountIdOptions"
          :placeholder="t('select_account')"
          :error="editForm.errors.account_id"
        />
        <div class="flex items-start gap-3">
          <input
            id="edit-is_mixed_use"
            v-model="editForm.is_mixed_use"
            type="checkbox"
            class="mt-1 h-4 w-4 rounded border-[hsl(var(--input))]"
          />
          <div>
            <label for="edit-is_mixed_use" class="text-sm font-medium">{{ t('mixed_use_label') }}</label>
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('mixed_use_tooltip') }}</p>
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <Button type="button" variant="outline" @click="showEditModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="editForm.processing" :loading="editForm.processing">{{ t('save_changes') }}</Button>
        </div>
      </form>
    </Modal>

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :open="showDeleteDialog"
      :title="t('delete_bank_account')"
      :message="t('delete_bank_account_confirm', { name: bankAccount.name })"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="showDeleteDialog = false"
    />
  </AppLayout>
</template>
