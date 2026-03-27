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
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { formatCurrency, formatDate } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'
import { Pencil, Trash2, Copy, Download, Paperclip, Ban } from 'lucide-vue-next'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'

const props = defineProps({
  invoice: Object,
  justificatifUrl: { type: String, default: null },
  bankAccounts: { type: Array, default: () => [] },
})

const { t } = useTranslations()

const showPaymentModal = ref(false)
const showDeleteDialog = ref(false)
const showCancelDialog = ref(false)
const deleting = ref(false)
const cancelling = ref(false)

const finalizeForm = useForm({})
const paymentForm = useForm({
  amount: '',
  payment_date: new Date().toISOString().slice(0, 10),
  payment_method: 'bank',
  reference: '',
  bank_account_code: '',
})

const amountDue = computed(() => {
  const paid = (props.invoice?.payments ?? []).reduce((sum, p) => sum + parseFloat(p.amount || 0), 0)
  return Math.max(0, parseFloat(props.invoice?.total || 0) - paid)
})

function finalize() {
  finalizeForm.post(`/invoices/${props.invoice.id}/finalize`)
}

function recordPayment() {
  paymentForm.post(`/invoices/${props.invoice.id}/payment`, {
    onSuccess: () => {
      showPaymentModal.value = false
      paymentForm.reset()
    },
  })
}

function duplicate() {
  router.post(`/invoices/${props.invoice.id}/duplicate`)
}

function removeJustificatif() {
  router.delete(`/invoices/${props.invoice.id}/justificatif`)
}

function executeDelete() {
  deleting.value = true
  router.delete(`/invoices/${props.invoice.id}`, {
    onFinish: () => { deleting.value = false },
  })
}

function executeCancel() {
  cancelling.value = true
  router.post(`/invoices/${props.invoice.id}/cancel`, {}, {
    onFinish: () => {
      cancelling.value = false
      showCancelDialog.value = false
    },
  })
}

const lineColumns = computed(() => [
  { key: 'description', label: t('description') },
  { key: 'quantity', label: t('qty'), class: 'text-right' },
  { key: 'unit_price', label: t('unit_price'), class: 'text-right', format: (v) => formatCurrency(v) },
  { key: 'total', label: t('total'), class: 'text-right', format: (v, row) => formatCurrency(row.quantity * row.unit_price) },
])

const paymentColumns = computed(() => [
  { key: 'payment_date', label: t('date'), format: (v) => formatDate(v) },
  { key: 'amount', label: t('amount'), class: 'text-right', format: (v) => formatCurrency(v) },
  { key: 'payment_method', label: t('payment_method') },
  { key: 'reference', label: t('reference') },
])

const statusVariant = {
  draft: 'secondary',
  sent: 'default',
  paid: 'success',
  overdue: 'destructive',
  cancelled: 'outline',
}

const paymentMethodOptions = [
  { value: 'bank', label: t('bank_transfer') },
  { value: 'cash', label: t('cash') },
  { value: 'card', label: t('card') },
]

const bankAccountOptions = computed(() =>
  props.bankAccounts.map(ba => ({ value: ba.id.toString(), label: `${ba.name}${ba.iban ? ` (${ba.iban})` : ''}` }))
)
</script>

<template>
  <AppLayout :title="`Invoice ${invoice?.number}`" help-page="invoices">
    <Breadcrumb :items="[
      { label: t('invoices'), href: '/invoices' },
      { label: invoice?.number },
    ]" />
    <div class="max-w-5xl space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div>
            <Badge :variant="statusVariant[invoice?.status] ?? 'secondary'" class="mb-1">
              {{ invoice?.status }}
            </Badge>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">
              {{ invoice?.customer?.name }} &middot; {{ t('issued') }} {{ formatDate(invoice?.issue_date) }} &middot; {{ t('due') }} {{ formatDate(invoice?.due_date) }}
            </p>
          </div>
        </div>
        <div class="flex gap-2">
          <Button
            v-if="invoice?.status === 'draft'"
            as="a"
            :href="`/invoices/${invoice.id}/edit`"
            variant="outline"
            size="sm"
          >
            <Pencil class="mr-1 h-4 w-4" />
            {{ t('edit') }}
          </Button>
          <Button
            variant="outline"
            size="sm"
            @click="duplicate"
          >
            <Copy class="mr-1 h-4 w-4" />
            {{ t('duplicate') }}
          </Button>
          <Button
            v-if="invoice?.status === 'draft'"
            variant="outline"
            :disabled="finalizeForm.processing"
            @click="finalize"
          >
            {{ t('finalize') }}
          </Button>
          <Button
            v-if="invoice?.status === 'sent' || invoice?.status === 'overdue'"
            @click="showPaymentModal = true"
          >
            {{ t('record_payment') }}
          </Button>
          <Button
            v-if="invoice?.status !== 'draft' && invoice?.status !== 'cancelled'"
            as="a"
            :href="`/invoices/${invoice.id}/qr-pdf`"
            variant="outline"
            size="sm"
          >
            <Download class="mr-1 h-4 w-4" />
            {{ t('download_qr_invoice') }}
          </Button>
          <Button
            v-if="invoice?.status === 'draft'"
            variant="destructive"
            size="sm"
            @click="showDeleteDialog = true"
          >
            <Trash2 class="mr-1 h-4 w-4" />
            {{ t('delete') }}
          </Button>
          <Button
            v-if="invoice?.status !== 'paid' && invoice?.status !== 'cancelled'"
            variant="outline"
            size="sm"
            @click="showCancelDialog = true"
          >
            <Ban class="mr-1 h-4 w-4" />
            {{ t('cancel_invoice') }}
          </Button>
        </div>
      </div>

      <!-- Line Items -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('line_items') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable :columns="lineColumns" :rows="invoice?.lines ?? []" />
          <div class="mt-4 flex justify-end">
            <div class="text-right">
              <p class="text-sm text-[hsl(var(--muted-foreground))]">{{ t('total') }}</p>
              <p class="text-2xl font-bold">{{ formatCurrency(invoice?.total) }}</p>
              <p v-if="invoice?.payments?.length" class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">
                {{ t('amount_due') }} {{ formatCurrency(amountDue) }}
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Payment History -->
      <Card v-if="invoice?.payments?.length">
        <CardHeader>
          <CardTitle>{{ t('payment_history') }}</CardTitle>
          <CardDescription>{{ invoice.payments.length }} {{ t('payments_recorded') }}</CardDescription>
        </CardHeader>
        <CardContent>
          <DataTable :columns="paymentColumns" :rows="invoice.payments" />
        </CardContent>
      </Card>

      <!-- Journal Entry -->
      <Card v-if="invoice?.journal_entry">
        <CardHeader>
          <CardTitle>{{ t('ledger_entry') }}</CardTitle>
          <CardDescription>{{ t('ref') }} {{ invoice.journal_entry.reference }}</CardDescription>
        </CardHeader>
        <CardContent>
          <DataTable
            :columns="[
              { key: 'account', label: t('account'), format: (v) => v ? `${v.code} ${v.name}` : '—' },
              { key: 'debit', label: t('debit'), class: 'text-right', format: (v) => v ? formatCurrency(v) : '' },
              { key: 'credit', label: t('credit'), class: 'text-right', format: (v) => v ? formatCurrency(v) : '' },
            ]"
            :rows="invoice.journal_entry.lines ?? []"
          />
        </CardContent>
      </Card>

      <!-- Justificatif -->
      <Card v-if="justificatifUrl">
        <CardHeader>
          <div class="flex items-center justify-between">
            <CardTitle class="flex items-center gap-2">
              <Paperclip class="h-4 w-4" />
              {{ t('justificatif') }}
            </CardTitle>
            <Button
              variant="ghost"
              size="sm"
              @click="removeJustificatif"
            >
              <Trash2 class="mr-1 h-4 w-4" />
              {{ t('remove') }}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <a :href="justificatifUrl" target="_blank" class="text-sm text-[hsl(var(--primary))] hover:underline">
            {{ t('view_justificatif') }}
          </a>
        </CardContent>
      </Card>
    </div>

    <!-- Payment Modal -->
    <Modal :open="showPaymentModal" :title="t('record_payment')" @close="showPaymentModal = false">
      <form class="space-y-4" @submit.prevent="recordPayment">
        <FormInput
          id="payment-amount"
          v-model="paymentForm.amount"
          type="number"
          :label="`${t('amount')} (${t('due')}: ${formatCurrency(amountDue)})`"
          :error="paymentForm.errors.amount"
          required
        />
        <FormInput
          id="payment-date"
          v-model="paymentForm.payment_date"
          type="date"
          :label="t('payment_date')"
          :error="paymentForm.errors.payment_date"
          required
        />
        <FormSelect
          id="payment-method"
          v-model="paymentForm.payment_method"
          :label="t('payment_method')"
          :options="paymentMethodOptions"
          :error="paymentForm.errors.payment_method"
          required
        />
        <FormInput
          id="payment-reference"
          v-model="paymentForm.reference"
          :label="t('reference_optional')"
          :error="paymentForm.errors.reference"
        />
        <FormSelect
          v-if="paymentForm.payment_method === 'bank' && bankAccountOptions.length"
          id="payment-bank-account"
          v-model="paymentForm.bank_account_code"
          :label="t('bank_account')"
          :options="bankAccountOptions"
          :placeholder="t('select_bank_account')"
          :error="paymentForm.errors.bank_account_code"
        />
        <div class="flex justify-end gap-3">
          <Button type="button" variant="outline" @click="showPaymentModal = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="paymentForm.processing">{{ t('record') }}</Button>
        </div>
      </form>
    </Modal>

    <!-- Delete Confirmation -->
    <ConfirmDialog
      :open="showDeleteDialog"
      :title="t('delete_invoice')"
      :message="t('delete_invoice_confirm', { number: invoice?.number })"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="showDeleteDialog = false"
    />

    <!-- Cancel Confirmation -->
    <ConfirmDialog
      :open="showCancelDialog"
      :title="t('cancel_invoice')"
      :message="t('cancel_invoice_confirm', { number: invoice?.number })"
      :confirm-label="t('cancel_invoice')"
      :processing="cancelling"
      @confirm="executeCancel"
      @cancel="showCancelDialog = false"
    />
  </AppLayout>
</template>
