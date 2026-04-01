<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'

const cantons = ['AG','AI','AR','BE','BL','BS','FR','GE','GL','GR','JU','LU','NE','NW','OW','SG','SH','SO','SZ','TG','TI','UR','VD','VS','ZG','ZH']

const form = useForm({
  user_name: '',
  user_email: '',
  user_password: '',
  user_password_confirmation: '',
  org_name: '',
  org_legal_name: '',
  org_address: '',
  org_city: '',
  org_postal_code: '',
  org_canton: '',
  org_vat_number: '',
  currency: 'CHF',
  locale: 'en',
})

function submit() {
  form.post('/setup')
}

const { t } = useTranslations()

const currencyOptions = [
  { value: 'CHF', label: t('chf_label') },
  { value: 'EUR', label: t('eur_label') },
  { value: 'USD', label: t('usd_label') },
]

const localeOptions = [
  { value: 'en', label: t('locale_en') },
  { value: 'fr', label: t('locale_fr') },
  { value: 'de', label: t('locale_de') },
  { value: 'it', label: t('locale_it') },
]
</script>

<template>
  <Head title="Setup Wizard" />
  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--background))] p-8">
    <Card class="w-full max-w-2xl">
      <CardHeader>
        <CardTitle class="text-3xl">{{ t('welcome') }}</CardTitle>
        <CardDescription>{{ t('setup_welcome') }}</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Admin User -->
          <fieldset class="space-y-6">
            <legend class="text-lg font-semibold">{{ t('admin_account') }}</legend>
            <FormInput id="user_name" v-model="form.user_name" :label="t('full_name')" :error="form.errors.user_name" required />
            <FormInput id="user_email" v-model="form.user_email" type="email" :label="t('email')" :error="form.errors.user_email" required />
            <FormInput id="user_password" v-model="form.user_password" type="password" :label="t('password')" :error="form.errors.user_password" required />
            <FormInput id="user_password_confirmation" v-model="form.user_password_confirmation" type="password" :label="t('confirm_password')" required />
          </fieldset>

          <!-- Organization -->
          <fieldset class="space-y-6">
            <legend class="text-lg font-semibold">{{ t('organization') }}</legend>
            <FormInput id="org_name" v-model="form.org_name" :label="t('company_name')" :error="form.errors.org_name" required />
            <FormInput id="org_legal_name" v-model="form.org_legal_name" :label="t('legal_name_different')" />
            <FormInput id="org_address" v-model="form.org_address" :label="t('address')" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormInput id="org_city" v-model="form.org_city" :label="t('city')" />
              <FormInput id="org_postal_code" v-model="form.org_postal_code" :label="t('postal_code')" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormSelect id="org_canton" v-model="form.org_canton" :label="t('canton')" :options="cantons" :placeholder="t('select')" />
              <FormInput id="org_vat_number" v-model="form.org_vat_number" :label="t('vat_number')" placeholder="CHE-123.456.789" />
            </div>
          </fieldset>

          <!-- Settings -->
          <fieldset class="space-y-6">
            <legend class="text-lg font-semibold">{{ t('settings') }}</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormSelect id="currency" v-model="form.currency" :label="t('currency')" :options="currencyOptions" required />
              <FormSelect id="locale" v-model="form.locale" :label="t('language')" :options="localeOptions" required />
            </div>
          </fieldset>

          <Button type="submit" class="w-full" :disabled="form.processing">
            {{ t('complete_setup') }}
          </Button>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
