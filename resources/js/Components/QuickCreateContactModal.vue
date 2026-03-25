<script setup>
import { ref, watch } from 'vue'
import Modal from '@/Components/UI/Modal.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'

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

const name = ref('')
const email = ref('')
const phone = ref('')
const address = ref('')
const city = ref('')
const postalCode = ref('')
const country = ref('CH')
const currency = ref('CHF')
const errors = ref({})
const saving = ref(false)

const countryOptions = [
  { value: 'CH', label: 'Switzerland' },
  { value: 'DE', label: 'Germany' },
  { value: 'AT', label: 'Austria' },
  { value: 'FR', label: 'France' },
  { value: 'IT', label: 'Italy' },
  { value: 'LI', label: 'Liechtenstein' },
]

const currencyOptions = [
  { value: 'CHF', label: 'CHF' },
  { value: 'EUR', label: 'EUR' },
  { value: 'USD', label: 'USD' },
  { value: 'GBP', label: 'GBP' },
]

watch(() => props.open, (val) => {
  if (val) {
    name.value = ''
    email.value = ''
    phone.value = ''
    address.value = ''
    city.value = ''
    postalCode.value = ''
    country.value = 'CH'
    currency.value = 'CHF'
    errors.value = {}
  }
})

async function submit() {
  saving.value = true
  errors.value = {}

  const endpoint = props.contactType === 'customer' ? '/customers' : '/suppliers'

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    || document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]

  const body = {
    name: name.value,
    email: email.value || null,
    phone: phone.value || null,
    address: address.value || null,
    city: city.value || null,
    postal_code: postalCode.value || null,
    country: country.value || null,
    currency: currency.value || null,
  }

  try {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    }
    if (csrfToken) {
      headers['X-XSRF-TOKEN'] = decodeURIComponent(csrfToken)
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
      errors.value = { name: 'An error occurred. Please try again.' }
      return
    }

    const data = await res.json()
    const contact = data.customer || data.supplier
    emit('created', contact)
    emit('close')
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
        <FormInput
          id="qc-phone"
          v-model="phone"
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
        <FormInput
          id="qc-postal-code"
          v-model="postalCode"
          :label="t('postal_code')"
          :error="errors.postal_code?.[0]"
        />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <FormSelect
          id="qc-country"
          v-model="country"
          :label="t('country')"
          :options="countryOptions"
          :error="errors.country?.[0]"
        />
        <FormSelect
          id="qc-currency"
          v-model="currency"
          :label="t('currency')"
          :options="currencyOptions"
          :error="errors.currency?.[0]"
        />
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <Button type="button" variant="outline" @click="$emit('close')">{{ t('cancel') }}</Button>
        <Button type="submit" :disabled="saving">
          {{ contactType === 'customer' ? t('create_customer') : t('create_supplier') }}
        </Button>
      </div>
    </form>
  </Modal>
</template>
