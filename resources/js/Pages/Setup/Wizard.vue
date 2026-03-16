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

const currencyOptions = [
  { value: 'CHF', label: 'CHF — Swiss Franc' },
  { value: 'EUR', label: 'EUR — Euro' },
  { value: 'USD', label: 'USD — US Dollar' },
]

const localeOptions = [
  { value: 'en', label: 'English' },
  { value: 'fr', label: 'Français' },
  { value: 'de', label: 'Deutsch' },
  { value: 'it', label: 'Italiano' },
  { value: 'rm', label: 'Rumantsch' },
]
</script>

<template>
  <Head title="Setup Wizard" />
  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--background))] p-8">
    <Card class="w-full max-w-2xl">
      <CardHeader>
        <CardTitle class="text-3xl">Welcome to Gäld</CardTitle>
        <CardDescription>Let's set up your accounting platform.</CardDescription>
      </CardHeader>
      <CardContent>
        <form class="space-y-8" @submit.prevent="submit">
          <!-- Admin User -->
          <fieldset class="space-y-4">
            <legend class="text-lg font-semibold">Admin Account</legend>
            <FormInput id="user_name" v-model="form.user_name" label="Full Name" :error="form.errors.user_name" required />
            <FormInput id="user_email" v-model="form.user_email" type="email" label="Email" :error="form.errors.user_email" required />
            <FormInput id="user_password" v-model="form.user_password" type="password" label="Password" :error="form.errors.user_password" required />
            <FormInput id="user_password_confirmation" v-model="form.user_password_confirmation" type="password" label="Confirm Password" required />
          </fieldset>

          <!-- Organization -->
          <fieldset class="space-y-4">
            <legend class="text-lg font-semibold">Organization</legend>
            <FormInput id="org_name" v-model="form.org_name" label="Company Name" :error="form.errors.org_name" required />
            <FormInput id="org_legal_name" v-model="form.org_legal_name" label="Legal Name (if different)" />
            <FormInput id="org_address" v-model="form.org_address" label="Address" />
            <div class="grid grid-cols-2 gap-4">
              <FormInput id="org_city" v-model="form.org_city" label="City" />
              <FormInput id="org_postal_code" v-model="form.org_postal_code" label="Postal Code" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <FormSelect id="org_canton" v-model="form.org_canton" label="Canton" :options="cantons" placeholder="— select —" />
              <FormInput id="org_vat_number" v-model="form.org_vat_number" label="VAT Number" placeholder="CHE-123.456.789" />
            </div>
          </fieldset>

          <!-- Settings -->
          <fieldset class="space-y-4">
            <legend class="text-lg font-semibold">Settings</legend>
            <div class="grid grid-cols-2 gap-4">
              <FormSelect id="currency" v-model="form.currency" label="Currency" :options="currencyOptions" required />
              <FormSelect id="locale" v-model="form.locale" label="Language" :options="localeOptions" required />
            </div>
          </fieldset>

          <Button type="submit" class="w-full" :disabled="form.processing">
            Complete Setup
          </Button>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
