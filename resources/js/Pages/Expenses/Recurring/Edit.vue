<script setup>
import { computed, watch } from 'vue'
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
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { currencyOptions } from '@/lib/contactOptions'

const props = defineProps({
  recurringExpense: { type: Object, required: true },
  suppliers: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  expenseAccounts: { type: Array, default: () => [] },
  vatRates: { type: Array, default: () => [] },
  frequencies: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const form = useForm({
  category: props.recurringExpense.category,
  description: props.recurringExpense.description ?? '',
  amount: (Number(props.recurringExpense.amount ?? 0) + Number(props.recurringExpense.vat_amount ?? 0)).toFixed(2),
  amount_basis: 'gross',
  vat_amount: props.recurringExpense.vat_amount ?? '',
  vat_rate_id: props.recurringExpense.vat_rate_id ?? '',
  vendor: props.recurringExpense.vendor ?? '',
  supplier_id: props.recurringExpense.supplier_id ?? '',
  currency: props.recurringExpense.currency,
  payment_method: props.recurringExpense.payment_method ?? '',
  expense_account_code: props.recurringExpense.expense_account_code ?? '',
  bank_account_code: props.recurringExpense.bank_account_code ?? '',
  frequency: props.recurringExpense.frequency,
  next_due_date: props.recurringExpense.next_due_date,
  end_date: props.recurringExpense.end_date ?? '',
  is_active: props.recurringExpense.is_active,
})

function submit() {
  form.put(`/expenses/recurring/${props.recurringExpense.uuid}`)
}

const supplierOptions = computed(() => [
  { value: '', label: '—' },
  ...props.suppliers.map(s => ({ value: s.id, label: s.name })),
])

const categoryOptions = computed(() =>
  props.categories.map(c => ({ value: c.name, label: c.name })),
)

const categoryByName = computed(() => new Map(props.categories.map(category => [category.name, category])))
let suggestedExpenseAccountCode = ''

const vatOptions = computed(() => [
  { value: '', label: t('no_vat') },
  ...props.vatRates.map(rate => ({ value: rate.id, label: `${rate.name} (${rate.rate}%)` })),
])

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
  if (form.amount_basis === 'gross') return Math.max(0, Number(form.amount || 0) - amount).toFixed(2)

  return ((amount * rate) / 100).toFixed(2)
})

const calculatedGrossAmount = computed(() => {
  if (form.amount_basis === 'gross') return Number(form.amount || 0).toFixed(2)

  return (Number(calculatedNetAmount.value) + Number(calculatedVatAmount.value)).toFixed(2)
})

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

watch(() => form.category, (category) => {
  const defaultAccountCode = categoryByName.value.get(category)?.default_expense_account?.code ?? ''

  if (!form.expense_account_code || form.expense_account_code === suggestedExpenseAccountCode) {
    form.expense_account_code = defaultAccountCode
  }

  suggestedExpenseAccountCode = defaultAccountCode
}, { immediate: true })

const expenseAccountOptions = computed(() => props.expenseAccounts
  .slice()
  .sort((a, b) => String(a.code).localeCompare(String(b.code)))
  .map(account => ({ value: account.code, label: `${account.code} — ${account.display_name ?? account.name}` })))

const frequencyOptions = computed(() =>
  props.frequencies.map(f => ({ value: f.value, label: f.label })),
)

const paymentMethodOptions = computed(() => [
  { value: '', label: '—' },
  { value: 'cash', label: t('payment_method_cash') },
  { value: 'card', label: t('payment_method_card') },
  { value: 'bank_transfer', label: t('payment_method_bank_transfer') },
  { value: 'other', label: t('payment_method_other') },
])
</script>

<template>
  <AppLayout :title="t('edit_recurring_expense')">
    <Breadcrumb :items="[
      { label: t('expenses'), href: '/expenses' },
      { label: t('recurring'), href: '/expenses/recurring' },
      { label: t('edit') },
    ]" class="mb-4" />

    <Card class="max-w-3xl">
      <CardHeader>
        <CardTitle>{{ t('edit_recurring_expense') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Schedule -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <FormSelect
              id="frequency"
              v-model="form.frequency"
              :label="t('frequency')"
              :options="frequencyOptions"
              :error="form.errors.frequency"
              required
            />
            <FormInput
              id="next_due_date"
              v-model="form.next_due_date"
              type="date"
              :label="t('next_due_date')"
              :error="form.errors.next_due_date"
              required
            />
            <FormInput
              id="end_date"
              v-model="form.end_date"
              type="date"
              :label="t('end_date')"
              :error="form.errors.end_date"
            />
          </div>

          <!-- Expense details -->
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormSelect
              id="category"
              v-model="form.category"
              :label="t('category')"
              :options="categoryOptions"
              :error="form.errors.category"
              required
            />
            <FormInput
              id="vendor"
              v-model="form.vendor"
              :label="t('vendor')"
              :error="form.errors.vendor"
            />
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
                step="0.01"
                :label="form.amount_basis === 'gross' ? t('amount_paid_incl_vat') : t('net_amount_excl_vat')"
                :hint="form.amount_basis === 'gross' ? t('amount_paid_incl_vat_hint') : t('net_amount_excl_vat_hint')"
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
            <FormSelect
              id="currency"
              v-model="form.currency"
              :label="t('currency')"
              :options="currencyOptions(t)"
              :error="form.errors.currency"
            />
            <FormSelect
              id="vat_rate_id"
              v-model="form.vat_rate_id"
              :label="t('vat_rate')"
              :options="vatOptions"
              :error="form.errors.vat_rate_id"
            />
            <SearchableSelect
              id="supplier_id"
              v-model="form.supplier_id"
              :label="t('supplier')"
              :options="supplierOptions"
              :error="form.errors.supplier_id"
            />
            <FormSelect
              id="payment_method"
              v-model="form.payment_method"
              :label="t('payment_method')"
              :options="paymentMethodOptions"
              :error="form.errors.payment_method"
            />
            <SearchableSelect
              id="expense_account_code"
              v-model="form.expense_account_code"
              :label="t('expense_account')"
              :options="expenseAccountOptions"
              :placeholder="t('select_account')"
              :error="form.errors.expense_account_code"
            />
          </div>

          <FormTextarea
            id="description"
            v-model="form.description"
            :label="t('description')"
            :error="form.errors.description"
            :rows="2"
          />

          <div class="flex flex-wrap justify-end gap-3">
            <Button as="a" href="/expenses/recurring" variant="outline">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="form.processing" :loading="form.processing">
              {{ t('save_changes') }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
