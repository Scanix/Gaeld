<script setup>
import { ref } from 'vue'
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
import { currencyOptions } from '@/lib/contactOptions'
import { Check, User, Building2, Calculator } from 'lucide-vue-next'

const cantons = ['AG','AI','AR','BE','BL','BS','FR','GE','GL','GR','JU','LU','NE','NW','OW','SG','SH','SO','SZ','TG','TI','UR','VD','VS','ZG','ZH']

const form = useForm({
  user_name: '',
  user_email: '',
  user_password: '',
  user_password_confirmation: '',
  business_type: '',
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

const localeOptions = [
  { value: 'en', label: t('locale_en') },
  { value: 'fr', label: t('locale_fr') },
  { value: 'de', label: t('locale_de') },
  { value: 'it', label: t('locale_it') },
]

const currentStep = ref(0)

const steps = [
  { key: 'account', label: () => t('step_account') },
  { key: 'business_type', label: () => t('step_business_type') },
  { key: 'organization', label: () => t('step_organization') },
  { key: 'settings', label: () => t('step_settings') },
]

const businessTypes = [
  { value: 'freelancer', icon: User, label: () => t('business_type_freelancer'), desc: () => t('business_type_freelancer_desc') },
  { value: 'sme', icon: Building2, label: () => t('business_type_sme'), desc: () => t('business_type_sme_desc') },
  { value: 'fiduciary', icon: Calculator, label: () => t('business_type_fiduciary'), desc: () => t('business_type_fiduciary_desc') },
]

function nextStep() {
  if (currentStep.value < steps.length - 1) {
    currentStep.value++
  }
}

function prevStep() {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}
</script>

<template>
  <Head :title="t('setup_wizard')" />
  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--background))] p-8">
    <Card class="w-full max-w-2xl">
      <CardHeader>
        <CardTitle class="text-3xl">{{ t('welcome') }}</CardTitle>
        <CardDescription>{{ t('setup_welcome') }}</CardDescription>

        <!-- Stepper indicator -->
        <nav aria-label="Setup progress" class="mt-6">
          <ol class="flex items-center gap-2">
            <li
              v-for="(step, i) in steps"
              :key="step.key"
              class="flex items-center gap-2"
            >
              <button
                type="button"
                class="flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
                :class="[
                  i === currentStep
                    ? 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]'
                    : i < currentStep
                      ? 'bg-[hsl(var(--primary)/0.15)] text-[hsl(var(--primary))]'
                      : 'bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]',
                ]"
                @click="i < currentStep ? currentStep = i : undefined"
              >
                <span
                  class="flex h-5 w-5 items-center justify-center rounded-full text-xs"
                  :class="i < currentStep ? '' : 'border border-current'"
                >
                  <Check v-if="i < currentStep" class="h-3 w-3" />
                  <span v-else>{{ i + 1 }}</span>
                </span>
                {{ step.label() }}
              </button>
              <span v-if="i < steps.length - 1" class="h-px w-6 bg-[hsl(var(--border))]" />
            </li>
          </ol>
        </nav>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Step 1: Admin User -->
          <fieldset v-show="currentStep === 0" class="space-y-6">
            <legend class="text-lg font-semibold">{{ t('admin_account') }}</legend>
            <FormInput id="user_name" v-model="form.user_name" :label="t('full_name')" :error="form.errors.user_name" required />
            <FormInput id="user_email" v-model="form.user_email" type="email" :label="t('email')" :error="form.errors.user_email" required />
            <FormInput id="user_password" v-model="form.user_password" type="password" :label="t('password')" :error="form.errors.user_password" required />
            <FormInput id="user_password_confirmation" v-model="form.user_password_confirmation" type="password" :label="t('confirm_password')" required />
          </fieldset>

          <!-- Step 2: Business Type -->
          <fieldset v-show="currentStep === 1" class="space-y-6">
            <legend class="text-lg font-semibold">{{ t('business_type') }}</legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <button
                v-for="bt in businessTypes"
                :key="bt.value"
                type="button"
                class="flex flex-col items-center gap-3 rounded-xl border-2 p-6 text-center transition-all hover:border-[hsl(var(--primary))] hover:bg-[hsl(var(--accent))]"
                :class="form.business_type === bt.value ? 'border-[hsl(var(--primary))] bg-[hsl(var(--accent))]' : 'border-[hsl(var(--border))]'"
                @click="form.business_type = bt.value"
              >
                <component :is="bt.icon" class="h-8 w-8" :class="form.business_type === bt.value ? 'text-[hsl(var(--primary))]' : 'text-[hsl(var(--muted-foreground))]'" />
                <span class="text-sm font-semibold">{{ bt.label() }}</span>
                <span class="text-xs text-[hsl(var(--muted-foreground))]">{{ bt.desc() }}</span>
              </button>
            </div>
          </fieldset>

          <!-- Step 3: Organization -->
          <fieldset v-show="currentStep === 2" class="space-y-6">
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

          <!-- Step 4: Settings -->
          <fieldset v-show="currentStep === 3" class="space-y-6">
            <legend class="text-lg font-semibold">{{ t('settings') }}</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormSelect id="currency" v-model="form.currency" :label="t('currency')" :options="currencyOptions(t)" required />
              <FormSelect id="locale" v-model="form.locale" :label="t('language')" :options="localeOptions" required />
            </div>
          </fieldset>

          <!-- Navigation buttons -->
          <div class="flex justify-between">
            <Button
              v-if="currentStep > 0"
              type="button"
              variant="outline"
              @click="prevStep"
            >
              {{ t('back') }}
            </Button>
            <span v-else />

            <Button
              v-if="currentStep < steps.length - 1"
              type="button"
              @click="nextStep"
            >
              {{ t('next') }}
            </Button>
            <Button
              v-else
              type="submit"
              :disabled="form.processing"
            >
              {{ t('complete_setup') }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </div>
</template>
