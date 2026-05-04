<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import MaskedInput from '@/Components/UI/MaskedInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'
import { currencyOptions } from '@/lib/contactOptions'
import { User, Building2, Calculator } from 'lucide-vue-next'

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
  business_type: '',
})

const businessTypes = [
  { value: 'freelancer', icon: User, label: () => t('business_type_freelancer'), desc: () => t('business_type_freelancer_desc') },
  { value: 'sme', icon: Building2, label: () => t('business_type_sme'), desc: () => t('business_type_sme_desc') },
  { value: 'fiduciary', icon: Calculator, label: () => t('business_type_fiduciary'), desc: () => t('business_type_fiduciary_desc') },
]

const businessTypeChartMap = {
  freelancer: 'swiss_freelancer',
  sme: 'swiss_sme',
  fiduciary: 'swiss_sme',
}

function selectBusinessType(value) {
  form.business_type = value
  if (businessTypeChartMap[value]) {
    form.chart_of_accounts = businessTypeChartMap[value]
  }
}

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

const localeOptions = [
  { value: 'en', label: t('locale_en') },
  { value: 'fr', label: t('locale_fr') },
  { value: 'de', label: t('locale_de') },
  { value: 'it', label: t('locale_it') },
]

function submit() {
  form.post('/organizations')
}
</script>

<template>
  <AppLayout :title="t('new_organization')">
    <Breadcrumb
      :items="[{ label: t('organizations'), href: '/organizations' }, { label: t('new_organization') }]"
      class="mb-4"
    />

    <div class="max-w-2xl">
      <Card>
        <CardHeader>
          <CardTitle>{{ t('new_organization') }}</CardTitle>
          <CardDescription>{{ t('onboarding_org_help') }}</CardDescription>
        </CardHeader>
        <CardContent>
          <form class="space-y-6" @submit.prevent="submit">
            <!-- Business type selector -->
            <fieldset class="space-y-4">
              <legend class="text-lg font-semibold">{{ t('business_type') }}</legend>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <button
                  v-for="bt in businessTypes"
                  :key="bt.value"
                  type="button"
                  class="flex flex-col items-center gap-3 rounded-xl border-2 p-5 text-center transition-all hover:border-[hsl(var(--primary))] hover:bg-[hsl(var(--accent))]"
                  :class="form.business_type === bt.value ? 'border-[hsl(var(--primary))] bg-[hsl(var(--accent))]' : 'border-[hsl(var(--border))]'"
                  @click="selectBusinessType(bt.value)"
                >
                  <component :is="bt.icon" class="h-7 w-7" :class="form.business_type === bt.value ? 'text-[hsl(var(--primary))]' : 'text-[hsl(var(--muted-foreground))]'" />
                  <span class="text-sm font-semibold">{{ bt.label() }}</span>
                  <span class="text-xs text-[hsl(var(--muted-foreground))]">{{ bt.desc() }}</span>
                </button>
              </div>
            </fieldset>

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
                <FormInput id="vat_number" v-model="form.vat_number" :label="t('vat_number')" :placeholder="t('placeholder_vat_uid')" :error="form.errors.vat_number" />
              </div>
            </fieldset>

            <fieldset class="space-y-6">
              <legend class="text-lg font-semibold">{{ t('settings') }}</legend>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormSelect id="currency" v-model="form.currency" :label="t('currency')" :options="currencyOptions(t)" required />
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

            <div class="flex items-center justify-end gap-3">
              <Button as="a" href="/organizations" variant="outline">{{ t('cancel') }}</Button>
              <Button type="submit" :disabled="form.processing">
                {{ t('create_organization_btn') }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
