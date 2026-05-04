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
import DropdownMenu from '@/Components/UI/DropdownMenu.vue'
import SharePrintButton from '@/Components/UI/SharePrintButton.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'
import { Pencil, Trash2, Copy, Download, Paperclip, Ban, FileMinus, Bell, Mail, Eye, X } from 'lucide-vue-next'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import HelpText from '@/Components/HelpText.vue'

const props = defineProps({
  invoice: Object,
  canForceDelete: { type: Boolean, default: false },
  justificatifUrl: { type: String, default: null },
  bankAccounts: { type: Array, default: () => [] },
  creditNotes: { type: Array, default: () => [] },
  relatedInvoice: { type: Object, default: null },
  reminderCount: { type: Number, default: 0 },
  lastRemindedAt: { type: String, default: null },
  hasQrIban: { type: Boolean, default: false },
})

const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const showPaymentModal = ref(false)
const showDeleteDialog = ref(false)
const showCancelDialog = ref(false)
const showPurgeDialog = ref(false)
const showJustificatifPreview = ref(false)
const deleting = ref(false)
const cancelling = ref(false)
const purging = ref(false)

const creditNoteForm = useForm({})
const sendForm = useForm({})
const reminderForm = useForm({})

function createCreditNote() {
  creditNoteForm.post(`/invoices/${props.invoice.id}/credit-note`)
}

function sendInvoice() {
  sendForm.post(`/invoices/${props.invoice.id}/send`)
}

function sendReminder() {
  reminderForm.post(`/invoices/${props.invoice.id}/reminder`)
}

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

const isOverdue = computed(() => {
  if (!props.invoice?.due_date) return false
  return props.invoice.status === 'sent' && new Date(props.invoice.due_date) < new Date()
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

function executePurge() {
  purging.value = true
  router.delete(`/invoices/${props.invoice.id}/purge`, {
    onFinish: () => {
      purging.value = false
      showPurgeDialog.value = false
    },
  })
}

const lineColumns = computed(() => [
  { key: 'description', label: t('description') },
  { key: 'quantity', label: t('qty'), class: 'text-right', format: (v, row) => row.type === 'discount' && row.discount_type === 'percentage' ? '—' : v },
  { key: 'unit_price', label: t('unit_price'), class: 'text-right', format: (v, row) => row.type === 'discount' && row.discount_type === 'percentage' ? `${v}%` : formatCurrency(v) },
  { key: 'vat_rate_id', label: t('vat'), class: 'text-right', format: (v, row) => {
    const vr = row.vat_rate ?? row.vatRate
    if (!vr) return '—'
    const amount = row.vat_amount != null ? formatCurrency(row.vat_amount) : null
    return amount ? `${vr.rate}% (${amount})` : `${vr.rate}%`
  } },
  { key: 'total', label: t('total'), class: 'text-right', format: (v, row) => {
    if (row.type === 'discount') return formatCurrency(-Math.abs(parseFloat(row.amount)))
    return formatCurrency(row.amount)
  }},
])

const paymentColumns = computed(() => [
  { key: 'payment_date', label: t('date'), format: (v) => formatDate(v) },
  { key: 'amount', label: t('amount'), class: 'text-right', format: (v) => formatCurrency(v) },
  { key: 'payment_method', label: t('payment_method'), format: (v) => paymentMethodOptions.find(o => o.value === v)?.label || v },
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
  props.bankAccounts
    .filter(ba => ba.ledger_account?.code)
    .map(ba => ({ value: ba.ledger_account.code, label: `${ba.name}${ba.iban ? ` (${ba.iban})` : ''}` }))
)
</script>

<template>
  <AppLayout :title="`${t('invoice')} ${invoice?.number}`" help-page="invoices">
    <Breadcrumb :items="[
      { label: t('invoices'), href: '/invoices' },
      { label: invoice?.number },
    ]" />

    <HelpText v-if="invoice?.status !== 'paid' && invoice?.status !== 'cancelled'" :title="t('help_reminders_title')" class="mb-6">
      <p>{{ t('help_reminders_text') }}</p>
    </HelpText>

    <div class="max-w-5xl space-y-6">
      <!-- Header -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div>
            <Badge :variant="statusVariant[invoice?.status] ?? 'secondary'" class="mb-1">
              {{ t('invoice_status_' + invoice?.status) }}
            </Badge>
            <p class="text-sm text-[hsl(var(--muted-foreground))]">
              {{ invoice?.customer?.name }} &middot; {{ t('issued') }} {{ formatDate(invoice?.issue_date) }} &middot; {{ t('due') }} {{ formatDate(invoice?.due_date) }}
            </p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <SharePrintButton :title="`${t('invoice')} ${invoice?.number}`" />
          <Button
            v-if="invoice?.status === 'draft'"
            as="a"
            :href="`/invoices/${invoice.id}/edit`"
            variant="outline"
            size="sm"
          >
            <Pencil class="h-4 w-4 sm:mr-1" />
            <span class="hidden sm:inline">{{ t('edit') }}</span>
          </Button>
          <Button
            v-if="invoice?.status === 'draft'"
            variant="outline"
            size="sm"
            :disabled="finalizeForm.processing"
            @click="finalize"
          >
            {{ t('finalize') }}
          </Button>
          <Button
            v-if="invoice?.status === 'sent' || invoice?.status === 'overdue'"
            size="sm"
            @click="showPaymentModal = true"
          >
            {{ t('record_payment') }}
          </Button>
          <Button
            v-if="hasQrIban && invoice?.status !== 'draft' && invoice?.status !== 'cancelled'"
            as="a"
            :href="`/invoices/${invoice.id}/qr-pdf`"
            variant="outline"
            size="sm"
          >
            <Download class="h-4 w-4 sm:mr-1" />
            <span class="hidden sm:inline">{{ t('download_qr_invoice') }}</span>
          </Button>
          <DropdownMenu>
            <template #default="{ close }">
              <button
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
                @click="duplicate(); close()"
              >
                <Copy class="h-4 w-4 shrink-0" />
                {{ t('duplicate') }}
              </button>
              <button
                v-if="invoice?.status === 'sent'"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
                :disabled="sendForm.processing"
                @click="sendInvoice(); close()"
              >
                <Mail class="h-4 w-4 shrink-0" />
                {{ t('send_invoice_email') }}
              </button>
              <button
                v-if="isOverdue"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
                :disabled="reminderForm.processing"
                @click="sendReminder(); close()"
              >
                <Bell class="h-4 w-4 shrink-0" />
                {{ t('send_reminder') }}
              </button>
              <button
                v-if="invoice?.type !== 'credit_note' && (invoice?.status === 'sent' || invoice?.status === 'paid')"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]"
                :disabled="creditNoteForm.processing"
                @click="createCreditNote(); close()"
              >
                <FileMinus class="h-4 w-4 shrink-0" />
                {{ t('create_credit_note') }}
              </button>
              <div
                v-if="(invoice?.status !== 'paid' && invoice?.status !== 'cancelled') || invoice?.status === 'draft'"
                class="my-1 border-t border-[hsl(var(--border))]"
              />
              <button
                v-if="invoice?.status !== 'paid' && invoice?.status !== 'cancelled'"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--destructive))] hover:bg-[hsl(var(--destructive))]/10"
                @click="showCancelDialog = true; close()"
              >
                <Ban class="h-4 w-4 shrink-0" />
                {{ t('cancel_invoice') }}
              </button>
              <button
                v-if="invoice?.status === 'draft' || invoice?.status === 'cancelled'"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--destructive))] hover:bg-[hsl(var(--destructive))]/10"
                @click="showDeleteDialog = true; close()"
              >
                <Trash2 class="h-4 w-4 shrink-0" />
                {{ t('delete') }}
              </button>
              <button
                v-if="canForceDelete"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-[hsl(var(--destructive))] hover:bg-[hsl(var(--destructive))]/10"
                @click="showPurgeDialog = true; close()"
              >
                <Trash2 class="h-4 w-4 shrink-0" />
                {{ t('purge_invoice') }}
              </button>
            </template>
          </DropdownMenu>
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
            <div class="w-48 space-y-1 text-sm">
              <div v-if="parseFloat(invoice?.vat_amount) > 0" class="flex justify-between text-[hsl(var(--muted-foreground))]">
                <span>{{ t('subtotal') }}</span>
                <span class="tabular-nums">{{ formatCurrency(invoice?.subtotal) }}</span>
              </div>
              <div v-if="parseFloat(invoice?.vat_amount) > 0" class="flex justify-between text-[hsl(var(--muted-foreground))]">
                <span>{{ t('vat_total') }}</span>
                <span class="tabular-nums">{{ formatCurrency(invoice?.vat_amount) }}</span>
              </div>
              <div class="flex justify-between border-t pt-1 font-semibold">
                <span>{{ t('total') }}</span>
                <span class="text-xl tabular-nums">{{ formatCurrency(invoice?.total) }}</span>
              </div>
              <p v-if="invoice?.payments?.length" class="text-right text-[hsl(var(--muted-foreground))]">
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

      <!-- Reminder Info -->
      <Card v-if="reminderCount > 0 || isOverdue">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Bell class="h-4 w-4" />
            {{ t('reminders_sent') }}
          </CardTitle>
        </CardHeader>
        <CardContent class="space-y-1 text-sm">
          <p>{{ t('reminders_sent') }}: <span class="font-medium">{{ reminderCount }}</span></p>
          <p v-if="lastRemindedAt">{{ t('last_reminded') }}: <span class="font-medium">{{ formatDate(lastRemindedAt) }}</span></p>
        </CardContent>
      </Card>

      <!-- Original Invoice (when viewing a credit note) -->
      <Card v-if="relatedInvoice">
        <CardHeader>
          <CardTitle>{{ t('original_invoice') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <a :href="`/invoices/${relatedInvoice.id}`" class="text-sm text-[hsl(var(--primary))] hover:underline">
            {{ relatedInvoice.number }}
          </a>
        </CardContent>
      </Card>

      <!-- Related Credit Notes -->
      <Card v-if="creditNotes?.length">
        <CardHeader>
          <CardTitle>{{ t('related_credit_notes') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="space-y-1">
            <div v-for="cn in creditNotes" :key="cn.id">
              <a :href="`/invoices/${cn.id}`" class="text-sm text-[hsl(var(--primary))] hover:underline">
                {{ cn.number }}
              </a>
              <span class="ml-2 text-sm text-[hsl(var(--muted-foreground))]">{{ formatCurrency(cn.total) }}</span>
            </div>
          </div>
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
          <div class="flex flex-wrap items-center gap-3">
            <Button variant="outline" size="sm" @click="showJustificatifPreview = true">
              <Eye class="mr-1.5 h-4 w-4" />
              {{ t('view_justificatif') }}
            </Button>
            <a
              :href="justificatifUrl"
              class="inline-flex items-center gap-1.5 text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition-colors"
            >
              <Download class="h-4 w-4" />
              {{ t('download') }}
            </a>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Payment Modal -->
    <Modal :open="showPaymentModal" :title="t('record_payment')" @close="showPaymentModal = false">
      <form class="space-y-6" @submit.prevent="recordPayment">
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

    <!-- Purge Confirmation -->
    <ConfirmDialog
      :open="showPurgeDialog"
      :title="t('purge_invoice')"
      :message="t('purge_invoice_confirm', { number: invoice?.number })"
      :confirm-label="t('purge_invoice')"
      :processing="purging"
      @confirm="executePurge"
      @cancel="showPurgeDialog = false"
    />
  </AppLayout>

  <!-- Justificatif preview overlay -->
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="showJustificatifPreview" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/60" @click="showJustificatifPreview = false" />
        <div class="relative z-50 flex w-full max-w-4xl flex-col rounded-xl border border-[hsl(var(--border))] bg-[hsl(var(--background))] shadow-xl" style="max-height: 90dvh;">
          <div class="flex shrink-0 items-center justify-between border-b border-[hsl(var(--border))] px-4 py-3">
            <h2 class="text-base font-semibold">{{ t('justificatif') }}</h2>
            <div class="flex items-center gap-2">
              <a
                :href="justificatifUrl"
                class="inline-flex items-center gap-1.5 text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] transition-colors"
              >
                <Download class="h-4 w-4" />
                {{ t('download') }}
              </a>
              <Button variant="ghost" size="icon" :aria-label="t('close')" @click="showJustificatifPreview = false">
                <X class="h-4 w-4" />
              </Button>
            </div>
          </div>
          <div class="flex-1 overflow-hidden p-2">
            <iframe
              :src="`${justificatifUrl}?inline=1`"
              class="h-full w-full rounded border-0"
              style="min-height: 70dvh;"
              :title="t('justificatif')"
            />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
