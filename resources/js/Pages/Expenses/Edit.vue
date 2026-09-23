<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormTextarea from '@/Components/UI/FormTextarea.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import QuickCreateContactModal from '@/Components/QuickCreateContactModal.vue'
import QuickReceiptButton from '@/Components/QuickReceiptButton.vue'
import FileUpload from '@/Components/UI/FileUpload.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import { useClosedFiscalYear } from '@/lib/useClosedFiscalYear'
import { useDocsUrl } from '@/lib/useDocsUrl'
import ClosedYearBanner from '@/Components/UI/ClosedYearBanner.vue'
import { Plus, FileText } from 'lucide-vue-next'

const props = defineProps({
  expense: Object,
  vatRates: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  expenseAccounts: { type: Array, default: () => [] },
  bankAccounts: { type: Array, default: () => [] },
  receiptUrl: { type: String, default: null },
})

const form = useForm({
  category: props.expense.category ?? '',
  description: props.expense.description ?? '',
  amount: (Number(props.expense.amount ?? 0) + Number(props.expense.vat_amount ?? 0)).toFixed(2),
  amount_basis: 'gross',
  vat_amount: props.expense.vat_amount ?? '',
  vat_rate_id: props.expense.vat_rate_id ?? '',
  date: props.expense.date?.slice(0, 10) ?? '',
  vendor: props.expense.vendor ?? '',
  supplier_id: props.expense.supplier_id ?? '',
  currency: props.expense.currency ?? 'CHF',
  payment_method: props.expense.payment_method ?? '',
  expense_account_code: props.expense.expense_account_code ?? '',
  bank_account_code: props.expense.bank_account_code ?? '',
  receipt: null,
})

const { forceClear } = useUnsavedChanges(computed(() => form.isDirty))

function submit() {
  forceClear.value = true
  form.post(`/expenses/${props.expense.id}`, {
    forceFormData: true,
    headers: { 'X-HTTP-Method-Override': 'PUT' },
    onError: () => { forceClear.value = false },
  })
}

const { t, expenseCategoryLabel } = useTranslations()
const { formatCurrency } = useFormatters()

const { isClosed: isDateClosed, closedYear } = useClosedFiscalYear(() => form.date)

const categoryByName = new Map(props.categories.map(category => [category.name, category]))
const categoryOptions = props.categories.map(c => ({ value: c.name, label: expenseCategoryLabel(c.name) }))
let suggestedExpenseAccountCode = ''

watch(() => form.category, (category) => {
  const defaultAccountCode = categoryByName.get(category)?.default_expense_account?.code ?? ''

  if (!form.expense_account_code || form.expense_account_code === suggestedExpenseAccountCode) {
    form.expense_account_code = defaultAccountCode
  }

  suggestedExpenseAccountCode = defaultAccountCode
}, { immediate: true })

const vatOptions = [
  { value: '', label: t('no_vat') },
  ...props.vatRates.map(v => ({ value: v.id, label: `${v.name} (${v.rate}%)` })),
]

const selectedVatRate = computed(() =>
  props.vatRates.find(rate => String(rate.id) === String(form.vat_rate_id))
)

const amountBasisOptions = [
  { value: 'gross', label: t('amount_basis_gross') },
  { value: 'net', label: t('amount_basis_net') },
]

const calculatedNetAmount = computed(() => {
  const amount = Number(form.amount)
  const rate = Number(selectedVatRate.value?.rate)

  if (!Number.isFinite(amount) || amount < 0) return '0.00'
  if (form.amount_basis !== 'gross' || !Number.isFinite(rate) || rate <= 0) return amount.toFixed(2)

  return (amount / (1 + rate / 100)).toFixed(2)
})

const calculatedVatAmount = computed(() => {
  const amount = Number(calculatedNetAmount.value)
  const rate = Number(selectedVatRate.value?.rate)

  if (!Number.isFinite(amount) || !Number.isFinite(rate) || rate <= 0) return '0.00'

  if (form.amount_basis === 'gross') {
    return Math.max(0, Number(form.amount || 0) - amount).toFixed(2)
  }

  return ((amount * rate) / 100).toFixed(2)
})

const calculatedGrossAmount = computed(() => {
  if (form.amount_basis === 'gross') return Number(form.amount || 0).toFixed(2)

  return (Number(calculatedNetAmount.value) + Number(calculatedVatAmount.value)).toFixed(2)
})

const amountLabel = computed(() => form.amount_basis === 'gross'
  ? t('amount_paid_incl_vat')
  : t('net_amount_excl_vat'))

const amountHint = computed(() => form.amount_basis === 'gross'
  ? t('amount_paid_incl_vat_hint')
  : t('net_amount_excl_vat_hint'))

function setAmountBasis(basis) {
  if (basis === form.amount_basis) return

  const netAmount = calculatedNetAmount.value
  const grossAmount = calculatedGrossAmount.value
  form.amount_basis = basis
  form.amount = basis === 'gross' ? grossAmount : netAmount
}

watch([() => form.amount, () => form.vat_rate_id], () => {
  form.vat_amount = calculatedVatAmount.value
}, { immediate: true })

const paymentMethodOptions = [
  { value: 'cash', label: t('payment_cash') },
  { value: 'card', label: t('payment_card') },
  { value: 'bank_transfer', label: t('payment_bank_transfer') },
  { value: 'other', label: t('payment_other') },
]

const expenseAccountOptions = computed(() =>
  props.expenseAccounts
    .slice()
    .sort((a, b) => String(a.code).localeCompare(String(b.code)))
    .map(a => ({ value: a.code, label: `${a.code} — ${a.display_name ?? a.name}` })),
)

const chartHelpHref = useDocsUrl().url('chart-of-accounts')

const bankAccountOptions = [
  ...props.bankAccounts
    .filter(ba => ba.ledger_account?.code)
    .map(ba => ({ value: ba.ledger_account.code, label: `${ba.name}${ba.iban ? ` (${ba.iban})` : ''}` })),
]

const supplierList = reactive([...props.suppliers])
const supplierOptions = ref([
  { value: '', label: '—' },
  ...supplierList.map(s => ({ value: s.id, label: s.name })),
])

const showCreateSupplier = ref(false)

function onSupplierCreated(supplier) {
  supplierList.push(supplier)
  supplierOptions.value = [
    { value: '', label: '—' },
    ...supplierList.map(s => ({ value: s.id, label: s.name })),
  ]
  form.supplier_id = supplier.id
  form.vendor = supplier.name
}

function onReceiptChange(file) {
  form.receipt = file ?? null
}

const isImage = computed(() => {
  if (!props.receiptUrl) return false
  return /\.(jpe?g|png|gif|webp)$/i.test(props.receiptUrl)
})
</script>

<template>
  <AppLayout :title="t('edit_expense')" help-page="expenses">
    <Breadcrumb :items="[{ label: t('expenses'), href: '/expenses' }, { label: t('edit_expense') }]" class="mb-4" />

    <ClosedYearBanner v-if="isDateClosed" :year="closedYear" />

    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('edit_expense') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Expense Details -->
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('expense_details') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex items-end gap-2">
              <SearchableSelect
                id="supplier_id"
                v-model="form.supplier_id"
                :label="t('vendor')"
                :options="supplierOptions"
                :placeholder="t('select_supplier')"
                :error="form.errors.supplier_id"
                class="flex-1"
              />
              <Button
                type="button"
                variant="outline"
                size="icon"
                class="mb-[2px] shrink-0"
                :title="t('new_supplier')"
                @click="showCreateSupplier = true"
              >
                <Plus class="h-4 w-4" />
              </Button>
            </div>
            <div class="space-y-3 sm:col-span-2">
              <fieldset>
                <legend class="mb-2 text-sm font-medium text-[hsl(var(--foreground))]">{{ t('amount_basis') }}</legend>
                <div class="inline-flex w-full rounded-md border border-[hsl(var(--input))] p-1 sm:w-auto">
                  <button
                    v-for="option in amountBasisOptions"
                    :key="option.value"
                    type="button"
                    class="min-h-9 flex-1 rounded px-3 text-sm font-medium transition-colors sm:min-w-28 sm:flex-none"
                    :class="form.amount_basis === option.value ? 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]' : 'text-[hsl(var(--muted-foreground))] hover:bg-[hsl(var(--accent))] hover:text-[hsl(var(--accent-foreground))]'"
                    :aria-pressed="form.amount_basis === option.value"
                    @click="setAmountBasis(option.value)"
                  >
                    {{ option.label }}
                  </button>
                </div>
              </fieldset>
              <FormInput
                id="amount"
                v-model="form.amount"
                type="number"
                :label="amountLabel"
                :hint="amountHint"
                :error="form.errors.amount"
                required
              />
              <div class="grid grid-cols-3 gap-2 rounded-md bg-[hsl(var(--muted)/0.45)] p-3 text-sm">
                <div>
                  <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('net_amount_excl_vat') }}</p>
                  <p class="mt-1 font-medium tabular-nums">{{ formatCurrency(calculatedNetAmount, form.currency) }}</p>
                </div>
                <div>
                  <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('vat_amount') }}</p>
                  <p class="mt-1 font-medium tabular-nums">{{ formatCurrency(calculatedVatAmount, form.currency) }}</p>
                </div>
                <div>
                  <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('gross_amount_incl_vat') }}</p>
                  <p class="mt-1 font-medium tabular-nums">{{ formatCurrency(calculatedGrossAmount, form.currency) }}</p>
                </div>
              </div>
            </div>
            <FormInput
              id="date"
              v-model="form.date"
              type="date"
              :label="t('date')"
              :error="form.errors.date"
              required
            />
            <FormSelect
              id="payment_method"
              v-model="form.payment_method"
              :label="t('payment_method')"
              :options="paymentMethodOptions"
              :placeholder="t('select')"
              :error="form.errors.payment_method"
            />
          </div>

          <!-- Categorization & VAT -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('categorization_vat') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormSelect
              id="category"
              v-model="form.category"
              :label="t('category')"
              :options="categoryOptions"
              :placeholder="t('select_category')"
              :error="form.errors.category"
              required
            />
            <FormSelect
              id="vat_rate_id"
              v-model="form.vat_rate_id"
              :label="t('vat_rate')"
              :options="vatOptions"
            />
          </div>

          <FormTextarea
            id="description"
            v-model="form.description"
            :label="t('description')"
          />

          <!-- Accounting -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('accounting') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <SearchableSelect
              id="expense_account_code"
              v-model="form.expense_account_code"
              :label="t('expense_account')"
              :options="expenseAccountOptions"
              :placeholder="t('select_account')"
              :error="form.errors.expense_account_code"
              :help-href="chartHelpHref"
            />
            <FormSelect
              id="bank_account_code"
              v-model="form.bank_account_code"
              :label="t('bank_account')"
              :options="bankAccountOptions"
              :placeholder="t('select_account')"
              :error="form.errors.bank_account_code"
            />
          </div>

          <!-- Attachment -->
          <hr class="border-[hsl(var(--border))]" />

          <FileUpload
            size="compact"
            :label="t('receipt')"
            :error="form.errors.receipt"
            @change="onReceiptChange"
          >
            <!-- Receipt preview -->
            <div v-if="receiptUrl && !form.receipt" class="mt-1">
              <p class="mb-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('receipt_preview') }}</p>
              <a :href="receiptUrl" target="_blank" rel="noopener">
                <img
                  v-if="isImage"
                  :src="receiptUrl"
                  :alt="t('receipt_preview')"
                  class="h-40 rounded-md border border-[hsl(var(--border))] object-contain"
                />
                <div
                  v-else
                  class="inline-flex items-center gap-2 rounded-md border border-[hsl(var(--border))] px-3 py-2 text-sm hover:bg-[hsl(var(--accent))]"
                >
                  <FileText class="h-5 w-5 text-[hsl(var(--muted-foreground))]" />
                  {{ t('receipt_attached') }}
                </div>
              </a>
            </div>
          </FileUpload>

          <div class="flex flex-wrap justify-end gap-3">
            <Button as="a" :href="`/expenses/${expense.id}`" variant="outline">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="form.processing || isDateClosed" :loading="form.processing" :title="isDateClosed ? t('fiscal_year_closed_action_disabled') : undefined">{{ t('save_changes') }}</Button>
          </div>
        </form>
      </CardContent>
    </Card>

    <QuickCreateContactModal
      :open="showCreateSupplier"
      contact-type="supplier"
      @close="showCreateSupplier = false"
      @created="onSupplierCreated"
    />

    <QuickReceiptButton />
  </AppLayout>
</template>
