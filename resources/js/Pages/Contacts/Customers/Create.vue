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
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import { countryOptions, currencyOptions } from '@/lib/contactOptions'
import { HelpCircle } from 'lucide-vue-next'

const { t } = useTranslations()

const form = useForm({
  type: 'organization',
  name: '',
  email: '',
  phone: '',
  address: '',
  city: '',
  postal_code: '',
  country: 'CH',
  vat_number: '',
  currency: 'CHF',
  payment_terms: '',
  internal_notes: '',
  notes: '',
})

const { forceClear } = useUnsavedChanges(computed(() => form.isDirty))

function submit() {
  forceClear.value = true
  form.post('/customers', {
    onError: () => { forceClear.value = false },
  })
}

const typeOptions = [
  { value: 'organization', label: t('organization') },
  { value: 'individual', label: t('individual') },
]
</script>

<template>
  <AppLayout :title="t('new_customer')" help-page="customers">
    <Breadcrumb :items="[{ label: t('customers'), href: '/customers' }, { label: t('new_customer') }]" class="mb-4" />

    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('new_customer') }}</CardTitle>
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
          </div>

          <!-- Notes -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('notes') }}</h3>

          <div class="relative">
            <FormTextarea
              id="internal_notes"
              v-model="form.internal_notes"
              :label="t('internal_notes')"
              :error="form.errors.internal_notes"
            />
            <Tooltip :content="t('tooltip_internal_notes')" side="top" class="absolute right-0 top-0">
              <HelpCircle class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))]" />
            </Tooltip>
          </div>
          <FormTextarea
            id="notes"
            v-model="form.notes"
            :label="t('notes')"
            :error="form.errors.notes"
          />

          <div class="flex flex-wrap justify-end gap-3">
            <Button as="a" href="/customers" variant="outline">
              {{ t('cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing" :loading="form.processing">
              {{ t('create_customer') }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
