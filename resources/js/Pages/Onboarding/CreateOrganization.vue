<script setup>
import { Head, useForm, Link, router } from '@inertiajs/vue3'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import MaskedInput from '@/Components/UI/MaskedInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { ArrowRightLeft } from 'lucide-vue-next'

const { t } = useTranslations()

const cantons = ['AG','AI','AR','BE','BL','BS','FR','GE','GL','GR','JU','LU','NE','NW','OW','SG','SH','SO','SZ','TG','TI','UR','VD','VS','ZG','ZH']

const form = useForm({
  name: '',
  legal_name: '',
  address: '',
  city: '',
  postal_code: '',
  canton: '',
  vat_number: '',
  currency: 'CHF',
  locale: 'en',
  chart_of_accounts: 'swiss_sme',
})

const chartOptions = [
  { value: 'swiss_sme', label: t('chart_swiss_sme') },
  { value: 'swiss_freelancer', label: t('chart_swiss_freelancer') },
  { value: 'swiss_association', label: t('chart_swiss_association') },
  { value: 'none', label: t('chart_none') },
]

const chartDescriptions = {
  swiss_sme: t('chart_swiss_sme_desc'),
  swiss_freelancer: t('chart_swiss_freelancer_desc'),
  swiss_association: t('chart_swiss_association_desc'),
}

function submit() {
  form.post('/onboarding')
}

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
  <Head :title="t('onboarding_title')" />

  <div class="flex min-h-screen items-center justify-center bg-[hsl(var(--muted))] p-6">
    <div class="w-full max-w-2xl">
      <div class="mb-8 text-center">
        <img src="/logo-wide.svg" alt="Gäld" class="mx-auto h-14 w-auto mb-4" />
        <h1 class="text-2xl font-bold">{{ t('onboarding_title') }}</h1>
        <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">{{ t('onboarding_description') }}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{{ t('organization') }}</CardTitle>
          <CardDescription>{{ t('onboarding_org_help') }}</CardDescription>
        </CardHeader>
        <CardContent>
          <form class="space-y-6" @submit.prevent="submit">
            <fieldset class="space-y-6">
              <FormInput id="name" v-model="form.name" :label="t('company_name')" :error="form.errors.name" required />
              <FormInput id="legal_name" v-model="form.legal_name" :label="t('legal_name_different')" :error="form.errors.legal_name" />
              <FormInput id="address" v-model="form.address" :label="t('address')" :error="form.errors.address" />
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormInput id="city" v-model="form.city" :label="t('city')" :error="form.errors.city" />
                <MaskedInput id="postal_code" v-model="form.postal_code" mask="postal" :label="t('postal_code')" :error="form.errors.postal_code" />
              </div>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormSelect id="canton" v-model="form.canton" :label="t('canton')" :options="cantons" :placeholder="t('select')" />
                <FormInput id="vat_number" v-model="form.vat_number" :label="t('vat_number')" placeholder="CHE-123.456.789" :error="form.errors.vat_number" />
              </div>
            </fieldset>

            <fieldset class="space-y-6">
              <legend class="text-lg font-semibold">{{ t('settings') }}</legend>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormSelect id="currency" v-model="form.currency" :label="t('currency')" :options="currencyOptions" required />
                <FormSelect id="locale" v-model="form.locale" :label="t('language')" :options="localeOptions" required />
              </div>
              <FormSelect id="chart_of_accounts" v-model="form.chart_of_accounts" :label="t('chart_of_accounts')" :options="chartOptions" required />
              <p v-if="chartDescriptions[form.chart_of_accounts]" class="text-xs text-[hsl(var(--muted-foreground))]">
                {{ chartDescriptions[form.chart_of_accounts] }}
              </p>
              <p class="text-xs text-[hsl(var(--muted-foreground))]">
                {{ t('chart_of_accounts_help') }}
              </p>
            </fieldset>

            <!-- Migration banner -->
            <div class="flex items-start gap-4 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--muted))]/30 p-4">
              <ArrowRightLeft class="h-6 w-6 shrink-0 mt-0.5 text-[hsl(var(--primary))]" />
              <div class="flex-1 min-w-0">
                <p class="font-medium text-sm">{{ t('migration.onboarding_import_title') }}</p>
                <p class="mt-0.5 text-xs text-[hsl(var(--muted-foreground))]">{{ t('migration.onboarding_import_desc') }}</p>
              </div>
            </div>

            <div class="flex items-center justify-between">
              <Link
                href="/logout"
                method="post"
                as="button"
                class="text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))] underline"
              >
                {{ t('logout') }}
              </Link>

              <Button type="submit" :disabled="form.processing">
                {{ t('create_organization_btn') }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
