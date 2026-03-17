<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'

const props = defineProps({
  vatRates: { type: Array, default: () => [] },
})

const form = useForm({
  category: '',
  description: '',
  amount: '',
  vat_amount: '',
  vat_rate_id: '',
  date: new Date().toISOString().slice(0, 10),
  vendor: '',
  currency: 'CHF',
})

function submit() {
  form.post('/expenses')
}

const { t } = useTranslations()

const categoryOptions = [
  { value: 'Office Supplies', label: t('cat_office_supplies') },
  { value: 'Travel', label: t('cat_travel') },
  { value: 'Software', label: t('cat_software') },
  { value: 'Professional Services', label: t('cat_professional_services') },
  { value: 'Marketing', label: t('cat_marketing') },
  { value: 'Rent', label: t('cat_rent') },
  { value: 'Utilities', label: t('cat_utilities') },
  { value: 'Insurance', label: t('cat_insurance') },
  { value: 'Other', label: t('cat_other') },
]

const vatOptions = [
  { value: '', label: t('no_vat') },
  ...props.vatRates.map(v => ({ value: v.id, label: `${v.name} (${v.rate}%)` })),
]
</script>

<template>
  <AppLayout :title="t('create_expense')" help-page="expenses">
    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('new_expense') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
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
            <FormInput
              id="vendor"
              v-model="form.vendor"
              :label="t('vendor')"
              placeholder="e.g. Digitec"
              :error="form.errors.vendor"
            />
            <FormInput
              id="amount"
              v-model="form.amount"
              type="number"
              :label="t('amount')"
              :error="form.errors.amount"
              required
            />
            <FormInput
              id="date"
              v-model="form.date"
              type="date"
              :label="t('date')"
              :error="form.errors.date"
              required
            />
            <FormSelect
              id="vat_rate_id"
              v-model="form.vat_rate_id"
              :label="t('vat_rate')"
              :options="vatOptions"
            />
            <FormInput
              id="vat_amount"
              v-model="form.vat_amount"
              type="number"
              :label="t('vat_amount')"
              :error="form.errors.vat_amount"
            />
          </div>

          <div>
            <label for="description" class="mb-1 block text-sm font-medium">{{ t('description') }}</label>
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              class="flex w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
            />
          </div>

          <div class="flex justify-end gap-3">
            <Button as="a" href="/expenses" variant="outline">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="form.processing">{{ t('create_expense') }}</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
