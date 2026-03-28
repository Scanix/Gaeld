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
import MaskedInput from '@/Components/UI/MaskedInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import { countryOptions, currencyOptions } from '@/lib/contactOptions'

const { t } = useTranslations()

const props = defineProps({
  customer: { type: Object, required: true },
})

const form = useForm({
  type: props.customer.type ?? 'organization',
  name: props.customer.name,
  email: props.customer.email ?? '',
  phone: props.customer.phone ?? '',
  address: props.customer.address ?? '',
  city: props.customer.city ?? '',
  postal_code: props.customer.postal_code ?? '',
  country: props.customer.country ?? 'CH',
  vat_number: props.customer.vat_number ?? '',
  currency: props.customer.currency ?? 'CHF',
  payment_terms: props.customer.payment_terms ?? '',
  internal_notes: props.customer.internal_notes ?? '',
  notes: props.customer.notes?.default ?? '',
})

useUnsavedChanges(computed(() => form.isDirty))

function submit() {
  form.put(`/customers/${props.customer.id}`)
}

const typeOptions = [
  { value: 'organization', label: t('organization') },
  { value: 'individual', label: t('individual') },
]
</script>

<template>
  <AppLayout :title="t('edit_customer')" help-page="customers">
    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('edit_customer') }}: {{ customer.name }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
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
            <FormInput
              id="address"
              v-model="form.address"
              :label="t('address')"
              :error="form.errors.address"
              class="sm:col-span-2"
            />
            <FormInput
              id="city"
              v-model="form.city"
              :label="t('city')"
              :error="form.errors.city"
            />
            <MaskedInput
              id="postal_code"
              v-model="form.postal_code"
              mask="postal"
              :label="t('postal_code')"
              :error="form.errors.postal_code"
            />
            <FormSelect
              id="country"
              v-model="form.country"
              :label="t('country')"
              :options="countryOptions(t)"
              :error="form.errors.country"
            />
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
            <FormInput
              id="internal_notes"
              v-model="form.internal_notes"
              :label="t('internal_notes')"
              :error="form.errors.internal_notes"
              class="sm:col-span-2"
            />
            <FormInput
              id="notes"
              v-model="form.notes"
              :label="t('notes')"
              :error="form.errors.notes"
              class="sm:col-span-2"
            />
          </div>

          <div class="flex justify-end gap-3">
            <Button as="a" :href="`/customers/${customer.id}`" variant="outline">
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
