<script setup>
import { computed, ref } from 'vue'
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
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import IbanHint from '@/Components/IbanHint.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import { countryOptions, currencyOptions } from '@/lib/contactOptions'

const { t } = useTranslations()

const props = defineProps({
  contact: { type: Object, required: true },
})

const form = useForm({
  type: props.contact.type ?? 'organization',
  name: props.contact.name,
  email: props.contact.email ?? '',
  phone: props.contact.phone ?? '',
  address: props.contact.address ?? '',
  city: props.contact.city ?? '',
  postal_code: props.contact.postal_code ?? '',
  country: props.contact.country ?? 'CH',
  vat_number: props.contact.vat_number ?? '',
  currency: props.contact.currency ?? 'CHF',
  iban: props.contact.iban ?? '',
  default_expense_category: props.contact.default_expense_category ?? '',
  payment_terms: props.contact.payment_terms ?? '',
})

const { forceClear } = useUnsavedChanges(computed(() => form.isDirty))

function submit() {
  forceClear.value = true
  form.put(`/contacts/${props.contact.uuid}`, {
    onError: () => { forceClear.value = false },
  })
}

const typeOptions = [
  { value: 'organization', label: t('organization') },
  { value: 'individual', label: t('individual') },
]

const activeTab = ref('general')
const tabs = [
  { key: 'general', label: 'general' },
  { key: 'billing', label: 'billing_details' },
]
</script>

<template>
  <AppLayout :title="t('edit_contact')" help-page="contacts">
    <Breadcrumb
      :items="[
        { label: t('contacts'), href: '/contacts' },
        { label: contact.name, href: `/contacts/${contact.uuid}` },
        { label: t('edit') },
      ]"
      class="mb-4"
    />

    <div class="max-w-2xl space-y-6">
      <div role="tablist" aria-label="Contact form" class="flex gap-1 rounded-lg bg-[hsl(var(--muted))] p-1">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          role="tab"
          :id="`tab-${tab.key}`"
          :aria-selected="activeTab === tab.key"
          :aria-controls="`tabpanel-${tab.key}`"
          :tabindex="activeTab === tab.key ? 0 : -1"
          :class="[
            'flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors',
            activeTab === tab.key
              ? 'bg-[hsl(var(--background))] text-[hsl(var(--foreground))] shadow-sm'
              : 'text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]',
          ]"
          @click="activeTab = tab.key"
        >
          {{ t(tab.label) }}
        </button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{{ t('edit_contact') }}: {{ contact.name }}</CardTitle>
        </CardHeader>
        <CardContent>
          <form class="space-y-4" @submit.prevent="submit">
            <div
              v-show="activeTab === 'general'"
              id="tabpanel-general"
              role="tabpanel"
              aria-labelledby="tab-general"
              class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            >
              <FormSelect
                id="type"
                v-model="form.type"
                :label="t('contact_type')"
                :options="typeOptions"
                :error="form.errors.type"
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
                class="sm:col-span-2"
              />
            </div>

            <div
              v-show="activeTab === 'billing'"
              id="tabpanel-billing"
              role="tabpanel"
              aria-labelledby="tab-billing"
              class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            >
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
                class="sm:col-span-2"
              />
              <div class="sm:col-span-2">
                <MaskedInput
                  id="iban"
                  v-model="form.iban"
                  mask="iban"
                  :label="t('iban_qr_iban')"
                  :placeholder="t('qr_iban_placeholder')"
                  :error="form.errors.iban"
                />
                <IbanHint :iban="form.iban" mode="any" />
              </div>
              <FormInput
                id="default_expense_category"
                v-model="form.default_expense_category"
                :label="t('default_expense_category')"
                :error="form.errors.default_expense_category"
                class="sm:col-span-2"
              />
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-[hsl(var(--border))] pt-4">
              <Button as="a" :href="`/contacts/${contact.uuid}`" variant="outline">
                {{ t('cancel') }}
              </Button>
              <Button type="submit" :disabled="form.processing" :loading="form.processing">
                {{ t('save_changes') }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
