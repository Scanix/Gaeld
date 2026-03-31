<script setup>
import { ref, watch } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import MaskedInput from '@/Components/UI/MaskedInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { countryOptions, currencyOptions } from '@/lib/contactOptions'

const props = defineProps({
  open: Boolean,
  /** 'customer' or 'supplier' */
  contactType: {
    type: String,
    required: true,
    validator: v => ['customer', 'supplier'].includes(v),
  },
})

const emit = defineEmits(['close', 'created'])

const { t } = useTranslations()

const contactSubType = ref('organization')
const name = ref('')
const email = ref('')
const phone = ref('')
const address = ref('')
const city = ref('')
const postalCode = ref('')
const country = ref('CH')
const currency = ref('CHF')
const vatNumber = ref('')
const paymentTerms = ref('')
const iban = ref('')
const defaultExpenseCategory = ref('')
const errors = ref({})
const formError = ref('')
const saving = ref(false)

const typeOptions = [
  { value: 'organization', label: t('organization') },
  { value: 'individual', label: t('individual') },
]

const categoryOptions = [
  { value: '', label: '—' },
  { value: 'office', label: 'Office' },
  { value: 'utilities', label: 'Utilities' },
  { value: 'software', label: 'Software' },
  { value: 'travel', label: 'Travel' },
  { value: 'marketing', label: 'Marketing' },
  { value: 'professional_services', label: 'Professional Services' },
  { value: 'equipment', label: 'Equipment' },
  { value: 'other', label: 'Other' },
]

watch(() => props.open, (val) => {
  if (val) {
    contactSubType.value = 'organization'
    name.value = ''
    email.value = ''
    phone.value = ''
    address.value = ''
    city.value = ''
    postalCode.value = ''
    country.value = 'CH'
    currency.value = 'CHF'
    vatNumber.value = ''
    paymentTerms.value = ''
    iban.value = ''
    defaultExpenseCategory.value = ''
    errors.value = {}
    formError.value = ''
    saving.value = false
  }
})

async function submit() {
  saving.value = true
  errors.value = {}
  formError.value = ''

  const endpoint = props.contactType === 'customer' ? '/customers' : '/suppliers'

  const csrfMetaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  const xsrfCookieToken = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]

  const body = {
    type: contactSubType.value,
    name: name.value,
    email: email.value || null,
    phone: phone.value || null,
    address: address.value || null,
    city: city.value || null,
    postal_code: postalCode.value || null,
    country: country.value || null,
    currency: currency.value || null,
    vat_number: vatNumber.value || null,
    payment_terms: paymentTerms.value || null,
    ...(props.contactType === 'supplier' ? {
      iban: iban.value || null,
      default_expense_category: defaultExpenseCategory.value || null,
    } : {}),
  }

  try {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    }
    if (csrfMetaToken) {
      headers['X-CSRF-TOKEN'] = csrfMetaToken
    }
    if (xsrfCookieToken) {
      headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfCookieToken)
    }

    const res = await fetch(endpoint, {
      method: 'POST',
      headers,
      credentials: 'same-origin',
      body: JSON.stringify(body),
    })

    if (res.status === 422) {
      const data = await res.json()
      errors.value = data.errors || {}
      return
    }

    if (!res.ok) {
      formError.value = t('save_error')
      return
    }

    const data = await res.json().catch(() => ({}))
    const contact = data.customer || data.supplier
    if (!contact?.id) {
      formError.value = t('save_error')
      return
    }

    emit('created', contact)
    emit('close')
  } catch {
    formError.value = t('save_error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal
    :open="open"
    :title="contactType === 'customer' ? t('new_customer') : t('new_supplier')"
    @close="$emit('close')"
  >
    <form class="space-y-4" @submit.prevent="submit">
      <FormSelect
        id="qc-type"
        v-model="contactSubType"
        :label="t('contact_type')"
        :options="typeOptions"
        :error="errors.type?.[0]"
      />
      <FormInput
        id="qc-name"
        v-model="name"
        :label="t('name')"
        :error="errors.name?.[0]"
        required
      />
      <div class="grid grid-cols-2 gap-3">
        <FormInput
          id="qc-email"
          v-model="email"
          type="email"
          :label="t('email')"
          :error="errors.email?.[0]"
        />
        <MaskedInput
          id="qc-phone"
          v-model="phone"
          mask="phone"
          :label="t('phone')"
          :error="errors.phone?.[0]"
        />
      </div>
      <FormInput
        id="qc-address"
        v-model="address"
        :label="t('address')"
        :error="errors.address?.[0]"
      />
      <div class="grid grid-cols-2 gap-3">
        <FormInput
          id="qc-city"
          v-model="city"
          :label="t('city')"
          :error="errors.city?.[0]"
        />
        <MaskedInput
          id="qc-postal-code"
          v-model="postalCode"
          mask="postal"
          :label="t('postal_code')"
          :error="errors.postal_code?.[0]"
        />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <FormSelect
          id="qc-country"
          v-model="country"
          :label="t('country')"
          :options="countryOptions(t)"
          :error="errors.country?.[0]"
        />
        <FormSelect
          id="qc-currency"
          v-model="currency"
          :label="t('currency')"
          :options="currencyOptions(t)"
          :error="errors.currency?.[0]"
        />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <FormInput
          id="qc-vat-number"
          v-model="vatNumber"
          :label="t('vat_number')"
          :error="errors.vat_number?.[0]"
          placeholder="CHE-123.456.789"
        />
        <FormInput
          id="qc-payment-terms"
          v-model="paymentTerms"
          :label="t('payment_terms')"
          :error="errors.payment_terms?.[0]"
          placeholder="30"
        />
      </div>

      <div v-if="contactType === 'supplier'" class="grid grid-cols-2 gap-3">
        <FormInput
          id="qc-iban"
          v-model="iban"
          label="IBAN / QR-IBAN"
          :error="errors.iban?.[0]"
          placeholder="CH56 0483 5012 3456 7800 9"
        />
        <FormSelect
          id="qc-default-expense-category"
          v-model="defaultExpenseCategory"
          :label="t('default_category')"
          :options="categoryOptions"
          :error="errors.default_expense_category?.[0]"
        />
      </div>

      <p v-if="formError" class="text-xs text-[hsl(var(--destructive))]">{{ formError }}</p>

      <div class="flex justify-end gap-3 pt-2">
        <Button type="button" variant="outline" @click="$emit('close')">{{ t('cancel') }}</Button>
        <Button type="submit" :disabled="saving">
          {{ contactType === 'customer' ? t('create_customer') : t('create_supplier') }}
        </Button>
      </div>
    </form>
  </Modal>
</template>
