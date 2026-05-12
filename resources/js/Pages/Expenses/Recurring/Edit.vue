<script setup>
import { computed } from 'vue'
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
import { currencyOptions } from '@/lib/contactOptions'

const props = defineProps({
  recurringExpense: { type: Object, required: true },
  suppliers: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  frequencies: { type: Array, default: () => [] },
})

const { t } = useTranslations()

const form = useForm({
  category: props.recurringExpense.category,
  description: props.recurringExpense.description ?? '',
  amount: props.recurringExpense.amount,
  vat_amount: props.recurringExpense.vat_amount ?? '',
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
  props.categories.map(c => ({ value: c, label: c })),
)

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
            <FormInput
              id="amount"
              v-model="form.amount"
              type="number"
              step="0.01"
              :label="t('amount')"
              :error="form.errors.amount"
              required
            />
            <FormSelect
              id="currency"
              v-model="form.currency"
              :label="t('currency')"
              :options="currencyOptions(t)"
              :error="form.errors.currency"
            />
            <FormInput
              id="vat_amount"
              v-model="form.vat_amount"
              type="number"
              step="0.01"
              :label="t('vat_amount')"
              :error="form.errors.vat_amount"
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
