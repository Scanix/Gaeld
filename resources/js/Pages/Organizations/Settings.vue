<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { Upload, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  organization: Object,
  hasLogo: Boolean,
})

const { t } = useTranslations()
const page = usePage()
const flash = computed(() => page.props.flash || {})

// --- Tabs ---
const activeTab = ref('general')
const tabs = [
  { key: 'general', label: 'settings_general' },
  { key: 'invoice', label: 'settings_invoice' },
  { key: 'communications', label: 'settings_communications' },
]

// --- General form ---
const generalForm = useForm({
  name: props.organization.name || '',
  legal_name: props.organization.legal_name || '',
  address: props.organization.address || '',
  city: props.organization.city || '',
  postal_code: props.organization.postal_code || '',
  canton: props.organization.canton || '',
  country: props.organization.country || 'CH',
  vat_number: props.organization.vat_number || '',
  currency: props.organization.currency || 'CHF',
  locale: props.organization.locale || 'en',
  require_two_factor: props.organization.require_two_factor || false,
  default_payment_terms_days: props.organization.default_payment_terms_days ?? 30,
})

function submitGeneral() {
  generalForm.put('/settings/general', { preserveScroll: true })
}

// --- Invoice form ---
const invoiceForm = useForm({
  invoice_header_text: props.organization.invoice_header_text || '',
  invoice_footer_text: props.organization.invoice_footer_text || '',
})

function submitInvoice() {
  invoiceForm.put('/settings/invoice', { preserveScroll: true })
}

// --- Logo upload ---
const logoFile = ref(null)
const logoPreview = ref(null)
const logoUploading = ref(false)

function onLogoSelect(event) {
  const file = event.target.files[0]
  if (!file) return
  logoFile.value = file
  logoPreview.value = URL.createObjectURL(file)
}

function uploadLogo() {
  if (!logoFile.value) return
  logoUploading.value = true
  const formData = new FormData()
  formData.append('logo', logoFile.value)

  router.post('/settings/invoice/logo', formData, {
    preserveScroll: true,
    onFinish: () => {
      logoUploading.value = false
      logoFile.value = null
      logoPreview.value = null
    },
  })
}

function deleteLogo() {
  router.delete('/settings/invoice/logo', { preserveScroll: true })
}

// --- Communications form ---
const commsForm = useForm({
  invoice_email_subject: props.organization.invoice_email_subject || '',
  invoice_email_body: props.organization.invoice_email_body || '',
})

function submitCommunications() {
  commsForm.put('/settings/communications', { preserveScroll: true })
}

const localeOptions = [
  { value: 'en', label: t('locale_en') },
  { value: 'fr', label: t('locale_fr') },
  { value: 'de', label: t('locale_de') },
  { value: 'it', label: t('locale_it') },
]

const currencyOptions = [
  { value: 'CHF', label: t('chf_label') },
  { value: 'EUR', label: t('eur_label') },
  { value: 'USD', label: t('usd_label') },
]

const cantonOptions = [
  { value: '', label: t('select_placeholder') },
  { value: 'AG', label: 'Aargau' }, { value: 'AI', label: 'Appenzell I.Rh.' },
  { value: 'AR', label: 'Appenzell A.Rh.' }, { value: 'BE', label: 'Bern' },
  { value: 'BL', label: 'Basel-Land' }, { value: 'BS', label: 'Basel-Stadt' },
  { value: 'FR', label: 'Fribourg' }, { value: 'GE', label: 'Genève' },
  { value: 'GL', label: 'Glarus' }, { value: 'GR', label: 'Graubünden' },
  { value: 'JU', label: 'Jura' }, { value: 'LU', label: 'Luzern' },
  { value: 'NE', label: 'Neuchâtel' }, { value: 'NW', label: 'Nidwalden' },
  { value: 'OW', label: 'Obwalden' }, { value: 'SG', label: 'St. Gallen' },
  { value: 'SH', label: 'Schaffhausen' }, { value: 'SO', label: 'Solothurn' },
  { value: 'SZ', label: 'Schwyz' }, { value: 'TG', label: 'Thurgau' },
  { value: 'TI', label: 'Ticino' }, { value: 'UR', label: 'Uri' },
  { value: 'VD', label: 'Vaud' }, { value: 'VS', label: 'Valais' },
  { value: 'ZG', label: 'Zug' }, { value: 'ZH', label: 'Zürich' },
]
</script>

<template>
  <AppLayout :title="t('organization_settings')" helpPage="settings">
    <div class="max-w-3xl space-y-6">
      <!-- Flash message -->
      <div v-if="flash.success" class="rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
        {{ flash.success }}
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 rounded-lg bg-[hsl(var(--muted))] p-1">
        <button
          v-for="tab in tabs"
          :key="tab.key"
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

      <!-- General Tab -->
      <div v-show="activeTab === 'general'">
        <Card>
          <CardHeader>
            <CardTitle>{{ t('settings_general_title') }}</CardTitle>
            <CardDescription>{{ t('settings_general_desc') }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-4" @submit.prevent="submitGeneral">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormInput
                  id="name"
                  v-model="generalForm.name"
                  :label="t('company_name')"
                  :error="generalForm.errors.name"
                  required
                />
                <FormInput
                  id="legal_name"
                  v-model="generalForm.legal_name"
                  :label="t('legal_name_different')"
                  :error="generalForm.errors.legal_name"
                />
              </div>

              <FormInput
                id="address"
                v-model="generalForm.address"
                :label="t('address')"
                :error="generalForm.errors.address"
              />

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <FormInput
                  id="postal_code"
                  v-model="generalForm.postal_code"
                  :label="t('postal_code')"
                  :error="generalForm.errors.postal_code"
                />
                <FormInput
                  id="city"
                  v-model="generalForm.city"
                  :label="t('city')"
                  :error="generalForm.errors.city"
                />
                <FormSelect
                  id="canton"
                  v-model="generalForm.canton"
                  :label="t('canton')"
                  :options="cantonOptions"
                  :error="generalForm.errors.canton"
                />
              </div>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormInput
                  id="vat_number"
                  v-model="generalForm.vat_number"
                  :label="t('vat_number')"
                  :error="generalForm.errors.vat_number"
                  placeholder="CHE-123.456.789"
                />
                <FormSelect
                  id="currency"
                  v-model="generalForm.currency"
                  :label="t('currency')"
                  :options="currencyOptions"
                  :error="generalForm.errors.currency"
                />
              </div>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormSelect
                  id="locale"
                  v-model="generalForm.locale"
                  :label="t('language')"
                  :options="localeOptions"
                  :error="generalForm.errors.locale"
                />
                <FormInput
                  id="default_payment_terms_days"
                  v-model="generalForm.default_payment_terms_days"
                  :label="t('default_payment_terms')"
                  type="number"
                  :error="generalForm.errors.default_payment_terms_days"
                />
              </div>

              <div class="flex items-center gap-3">
                <input
                  id="require_two_factor"
                  v-model="generalForm.require_two_factor"
                  type="checkbox"
                  class="h-4 w-4 rounded border-[hsl(var(--input))] text-[hsl(var(--primary))] focus:ring-[hsl(var(--ring))]"
                >
                <div>
                  <label for="require_two_factor" class="text-sm font-medium">{{ t('require_two_factor') }}</label>
                  <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('require_two_factor_desc') }}</p>
                </div>
              </div>

              <div class="flex justify-end">
                <Button type="submit" :disabled="generalForm.processing">
                  {{ t('save_changes') }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>

      <!-- Invoice Tab -->
      <div v-show="activeTab === 'invoice'">
        <!-- Logo -->
        <Card class="mb-6">
          <CardHeader>
            <CardTitle>{{ t('settings_logo_title') }}</CardTitle>
            <CardDescription>{{ t('settings_logo_desc') }}</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="space-y-4">
              <!-- Current logo preview -->
              <div v-if="hasLogo && !logoPreview" class="flex items-center gap-4">
                <img
                  :src="`/settings/logo?t=${Date.now()}`"
                  alt="Organization logo"
                  class="h-16 max-w-[120px] rounded border border-[hsl(var(--border))] object-contain p-1"
                >
                <Button variant="destructive" size="sm" @click="deleteLogo">
                  <Trash2 class="mr-1 h-4 w-4" />
                  {{ t('remove') }}
                </Button>
              </div>

              <!-- New logo preview -->
              <div v-if="logoPreview" class="flex items-center gap-4">
                <img
                  :src="logoPreview"
                  alt="New logo preview"
                  class="h-16 max-w-[120px] rounded border border-[hsl(var(--border))] object-contain p-1"
                >
                <Button size="sm" :disabled="logoUploading" @click="uploadLogo">
                  <Upload class="mr-1 h-4 w-4" />
                  {{ logoUploading ? t('uploading') + '…' : t('save_changes') }}
                </Button>
              </div>

              <!-- Upload input -->
              <div>
                <label
                  class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-[hsl(var(--border))] px-6 py-4 text-sm text-[hsl(var(--muted-foreground))] transition-colors hover:border-[hsl(var(--primary))] hover:text-[hsl(var(--foreground))]"
                >
                  <Upload class="h-5 w-5" />
                  {{ t('settings_logo_upload') }}
                  <input
                    type="file"
                    accept="image/png,image/jpeg"
                    class="hidden"
                    @change="onLogoSelect"
                  >
                </label>
                <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('settings_logo_hint') }}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Invoice texts -->
        <Card>
          <CardHeader>
            <CardTitle>{{ t('settings_invoice_texts_title') }}</CardTitle>
            <CardDescription>{{ t('settings_invoice_texts_desc') }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-4" @submit.prevent="submitInvoice">
              <div>
                <label for="invoice_header_text" class="mb-1 block text-sm font-medium">{{ t('settings_invoice_header') }}</label>
                <textarea
                  id="invoice_header_text"
                  v-model="invoiceForm.invoice_header_text"
                  rows="3"
                  :placeholder="t('settings_invoice_header_placeholder')"
                  class="flex w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
                />
                <p v-if="invoiceForm.errors.invoice_header_text" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ invoiceForm.errors.invoice_header_text }}</p>
              </div>

              <div>
                <label for="invoice_footer_text" class="mb-1 block text-sm font-medium">{{ t('settings_invoice_footer') }}</label>
                <textarea
                  id="invoice_footer_text"
                  v-model="invoiceForm.invoice_footer_text"
                  rows="3"
                  :placeholder="t('settings_invoice_footer_placeholder')"
                  class="flex w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
                />
                <p v-if="invoiceForm.errors.invoice_footer_text" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ invoiceForm.errors.invoice_footer_text }}</p>
              </div>

              <div class="flex justify-end">
                <Button type="submit" :disabled="invoiceForm.processing">
                  {{ t('save_changes') }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>

      <!-- Communications Tab -->
      <div v-show="activeTab === 'communications'">
        <Card>
          <CardHeader>
            <CardTitle>{{ t('settings_comms_title') }}</CardTitle>
            <CardDescription>{{ t('settings_comms_desc') }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-4" @submit.prevent="submitCommunications">
              <FormInput
                id="invoice_email_subject"
                v-model="commsForm.invoice_email_subject"
                :label="t('settings_email_subject')"
                :error="commsForm.errors.invoice_email_subject"
                :placeholder="t('settings_email_subject_placeholder')"
              />

              <div>
                <label for="invoice_email_body" class="mb-1 block text-sm font-medium">{{ t('settings_email_body') }}</label>
                <textarea
                  id="invoice_email_body"
                  v-model="commsForm.invoice_email_body"
                  rows="6"
                  :placeholder="t('settings_email_body_placeholder')"
                  class="flex w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
                />
                <p v-if="commsForm.errors.invoice_email_body" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ commsForm.errors.invoice_email_body }}</p>
              </div>

              <div class="rounded-md bg-[hsl(var(--muted))] p-3">
                <p class="text-xs font-medium text-[hsl(var(--muted-foreground))]">{{ t('settings_email_placeholders_title') }}</p>
                <div class="mt-1 flex flex-wrap gap-2">
                  <code class="rounded bg-[hsl(var(--background))] px-1.5 py-0.5 text-xs">{customer_name}</code>
                  <code class="rounded bg-[hsl(var(--background))] px-1.5 py-0.5 text-xs">{invoice_number}</code>
                  <code class="rounded bg-[hsl(var(--background))] px-1.5 py-0.5 text-xs">{amount}</code>
                  <code class="rounded bg-[hsl(var(--background))] px-1.5 py-0.5 text-xs">{due_date}</code>
                  <code class="rounded bg-[hsl(var(--background))] px-1.5 py-0.5 text-xs">{organization_name}</code>
                </div>
              </div>

              <div class="flex justify-end">
                <Button type="submit" :disabled="commsForm.processing">
                  {{ t('save_changes') }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
