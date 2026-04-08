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
import MaskedInput from '@/Components/UI/MaskedInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import IbanHint from '@/Components/IbanHint.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import { countryOptions, currencyOptions, supplierCategoryOptions } from '@/lib/contactOptions'

const { t } = useTranslations()

const props = defineProps({
  supplier: { type: Object, required: true },
})

const form = useForm({
  type: props.supplier.type ?? 'organization',
  name: props.supplier.name,
  email: props.supplier.email ?? '',
  phone: props.supplier.phone ?? '',
  address: props.supplier.address ?? '',
  city: props.supplier.city ?? '',
  postal_code: props.supplier.postal_code ?? '',
  country: props.supplier.country ?? 'CH',
  vat_number: props.supplier.vat_number ?? '',
  currency: props.supplier.currency ?? 'CHF',
  payment_terms: props.supplier.payment_terms ?? '',
  iban: props.supplier.iban ?? '',
  default_expense_category: props.supplier.default_expense_category ?? '',
  internal_notes: props.supplier.internal_notes ?? '',
  notes: props.supplier.notes?.default ?? '',
})

const { forceClear } = useUnsavedChanges(computed(() => form.isDirty))

function submit() {
  forceClear.value = true
  form.put(`/suppliers/${props.supplier.uuid}`, {
    onError: () => { forceClear.value = false },
  })
}

const categoryOptions = supplierCategoryOptions(t)

const typeOptions = [
  { value: 'organization', label: t('organization') },
  { value: 'individual', label: t('individual') },
]
</script>

<template>
  <AppLayout :title="t('edit_supplier')" help-page="suppliers">
    <Breadcrumb :items="[{ label: t('suppliers'), href: '/suppliers' }, { label: supplier.name, href: `/suppliers/${supplier.uuid}` }, { label: t('edit') }]" class="mb-4" />

    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('edit_supplier') }}: {{ supplier.name }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Contact Information -->
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('contact_information') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormSelect
              id="type"
              v-model="form.type"
              :label="t('contact_type')"
              :options="typeOptions"
              :error="form.errors.type"
              class="sm:col-span-2"
            />
            <FormInput
              id="name"
              v-model="form.name"
              :label="t('name')"
              :error="form.errors.name"
              required
              class="sm:col-span-2"
            />
            <FormInput
              id="email"
              v-model="form.email"
              type="email"
              :label="t('email')"
              :error="form.errors.email"
            />
            <MaskedInput
              id="phone"
              v-model="form.phone"
              mask="phone"
              :label="t('phone')"
              :error="form.errors.phone"
            />
          </div>

          <!-- Address -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('address_details') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormInput
              id="address"
              v-model="form.address"
              :label="t('address')"
              :error="form.errors.address"
              class="sm:col-span-2"
            />
            <MaskedInput
              id="postal_code"
              v-model="form.postal_code"
              mask="postal"
              :label="t('postal_code')"
              :error="form.errors.postal_code"
            />
            <FormInput
              id="city"
              v-model="form.city"
              :label="t('city')"
              :error="form.errors.city"
            />
            <FormSelect
              id="country"
              v-model="form.country"
              :label="t('country')"
              :options="countryOptions(t)"
              :error="form.errors.country"
            />
          </div>

          <!-- Billing & Payment -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('billing_details') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormSelect
              id="currency"
              v-model="form.currency"
              :label="t('currency')"
              :options="currencyOptions(t)"
              :error="form.errors.currency"
            />
            <FormInput
              id="vat_number"
              v-model="form.vat_number"
              :label="t('vat_number')"
              :placeholder="t('vat_number_placeholder')"
              :error="form.errors.vat_number"
            />
            <FormInput
              id="payment_terms"
              v-model="form.payment_terms"
              :label="t('payment_terms')"
              :placeholder="t('payment_terms_placeholder')"
              :error="form.errors.payment_terms"
            />
            <MaskedInput
              id="iban"
              v-model="form.iban"
              mask="iban"
              :label="t('iban')"
              :placeholder="t('qr_iban_placeholder')"
              :error="form.errors.iban"
              class="sm:col-span-2"
            />
            <IbanHint :iban="form.iban" mode="any" class="sm:col-span-2" />
            <FormSelect
              id="default_expense_category"
              v-model="form.default_expense_category"
              :label="t('default_category')"
              :options="categoryOptions"
              :error="form.errors.default_expense_category"
            />
          </div>

          <!-- Notes -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('notes') }}</h3>
          <FormInput
            id="internal_notes"
            v-model="form.internal_notes"
            :label="t('internal_notes')"
            :error="form.errors.internal_notes"
          />

          <FormTextarea
            id="notes"
            v-model="form.notes"
            :label="t('notes')"
            :error="form.errors.notes"
          />

          <div class="flex flex-wrap justify-end gap-3">
            <Button as="a" :href="`/suppliers/${supplier.uuid}`" variant="outline">
              {{ t('cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
              {{ t('save') }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
