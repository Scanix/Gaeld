<script setup>
import { ref, watch } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormTextarea from '@/Components/UI/FormTextarea.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import FileUpload from '@/Components/UI/FileUpload.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useTranslations } from '@/lib/useTranslations'
import { currencyOptions } from '@/lib/contactOptions'
import IbanHint from '@/Components/IbanHint.vue'
import { Upload, Trash2, Plus } from 'lucide-vue-next'

const props = defineProps({
  organization: Object,
  hasLogo: Boolean,
  expenseCategories: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const page = usePage()

// --- Tabs ---
const activeTab = ref('general')
const tabs = [
  { key: 'general', label: 'settings_general' },
  { key: 'invoice', label: 'settings_invoice' },
  { key: 'communications', label: 'settings_communications' },
  { key: 'expenses', label: 'settings_expenses' },
]

// --- General form ---
const initialName = props.organization.name || ''
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

// Auto-sync legal_name when name changes, if legal_name still matches the original name
watch(() => generalForm.name, (newName) => {
  if (generalForm.legal_name === initialName || generalForm.legal_name === '') {
    generalForm.legal_name = newName
  }
})

function submitGeneral() {
  generalForm.put('/settings/general', { preserveScroll: true })
}

// --- Invoice form ---
const invoiceForm = useForm({
  invoice_header_text: props.organization.invoice_header_text || '',
  invoice_footer_text: props.organization.invoice_footer_text || '',
  default_invoice_notes: props.organization.default_invoice_notes || '',
  qr_iban: props.organization.qr_iban || '',
})

function submitInvoice() {
  invoiceForm.put('/settings/invoice', { preserveScroll: true })
}

// --- Logo upload ---
const logoFile = ref(null)
const logoPreview = ref(null)
const logoUploading = ref(false)

function onLogoSelect(file) {
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

// --- Expense categories ---
const newCategoryName = ref('')
const addingCategory = ref(false)
const categoryToDelete = ref(null)

function addCategory() {
  if (!newCategoryName.value.trim()) return
  addingCategory.value = true
  router.post('/settings/expense-categories', { name: newCategoryName.value.trim() }, {
    preserveScroll: true,
    onFinish: () => {
      newCategoryName.value = ''
      addingCategory.value = false
    },
  })
}

function confirmRemoveCategory(cat) {
  categoryToDelete.value = cat
}

function removeCategory() {
  if (!categoryToDelete.value) return
  router.delete(`/settings/expense-categories/${categoryToDelete.value.id}`, {
    preserveScroll: true,
    onFinish: () => { categoryToDelete.value = null },
  })
}

const localeOptions = [
  { value: 'en', label: t('locale_en') },
  { value: 'fr', label: t('locale_fr') },
  { value: 'de', label: t('locale_de') },
  { value: 'it', label: t('locale_it') },
]

const cantonOptions = [
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
  <AppLayout :title="t('organization_settings')" help-page="settings">
    <div class="max-w-3xl space-y-6">
      <!-- Tabs -->
      <div role="tablist" aria-label="Settings" class="flex gap-1 rounded-lg bg-[hsl(var(--muted))] p-1">
        <button
          v-for="tab in tabs"
          :key="tab.key"
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

      <!-- General Tab -->
      <div v-show="activeTab === 'general'" id="tabpanel-general" role="tabpanel" aria-labelledby="tab-general">
        <Card>
          <CardHeader>
            <CardTitle>{{ t('settings_general_title') }}</CardTitle>
            <CardDescription>{{ t('settings_general_desc') }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-6" @submit.prevent="submitGeneral">
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
                  :placeholder="t('select_placeholder')"
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
                  :options="currencyOptions(t)"
                  :error="generalForm.errors.currency"
                />
              </div>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <FormSelect
                    id="locale"
                    v-model="generalForm.locale"
                    :label="t('language')"
                    :options="localeOptions"
                    :error="generalForm.errors.locale"
                  />
                  <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('org_locale_hint') }}</p>
                </div>
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
                <Button type="submit" :disabled="generalForm.processing" :loading="generalForm.processing">
                  {{ t('save_changes') }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>

      <!-- Invoice Tab -->
      <div v-show="activeTab === 'invoice'" id="tabpanel-invoice" role="tabpanel" aria-labelledby="tab-invoice">
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
              <FileUpload
                accept="image/png,image/jpeg"
                :max-size-mb="2"
                :help-text="t('settings_logo_hint')"
                @change="onLogoSelect"
              />
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
            <form class="space-y-6" @submit.prevent="submitInvoice">
              <FormTextarea
                id="invoice_header_text"
                v-model="invoiceForm.invoice_header_text"
                :label="t('settings_invoice_header')"
                :placeholder="t('settings_invoice_header_placeholder')"
                :error="invoiceForm.errors.invoice_header_text"
              />

              <FormTextarea
                id="invoice_footer_text"
                v-model="invoiceForm.invoice_footer_text"
                :label="t('settings_invoice_footer')"
                :placeholder="t('settings_invoice_footer_placeholder')"
                :error="invoiceForm.errors.invoice_footer_text"
              />

              <FormTextarea
                id="default_invoice_notes"
                v-model="invoiceForm.default_invoice_notes"
                :label="t('settings_default_invoice_notes')"
                :placeholder="t('settings_default_invoice_notes_placeholder')"
                :error="invoiceForm.errors.default_invoice_notes"
              />

              <FormInput
                id="qr_iban"
                v-model="invoiceForm.qr_iban"
                :label="t('qr_iban')"
                :error="invoiceForm.errors.qr_iban"
                :placeholder="t('qr_iban_placeholder')"
              />
              <IbanHint :iban="invoiceForm.qr_iban" mode="qr" />

              <div class="flex justify-end">
                <Button type="submit" :disabled="invoiceForm.processing" :loading="invoiceForm.processing">
                  {{ t('save_changes') }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>

      <!-- Communications Tab -->
      <div v-show="activeTab === 'communications'" id="tabpanel-communications" role="tabpanel" aria-labelledby="tab-communications">
        <Card>
          <CardHeader>
            <CardTitle>{{ t('settings_comms_title') }}</CardTitle>
            <CardDescription>{{ t('settings_comms_desc') }}</CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-6" @submit.prevent="submitCommunications">
              <FormInput
                id="invoice_email_subject"
                v-model="commsForm.invoice_email_subject"
                :label="t('settings_email_subject')"
                :error="commsForm.errors.invoice_email_subject"
                :placeholder="t('settings_email_subject_placeholder')"
              />

              <FormTextarea
                id="invoice_email_body"
                v-model="commsForm.invoice_email_body"
                :label="t('settings_email_body')"
                :rows="6"
                :placeholder="t('settings_email_body_placeholder')"
                :error="commsForm.errors.invoice_email_body"
              />

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
                <Button type="submit" :disabled="commsForm.processing" :loading="commsForm.processing">
                  {{ t('save_changes') }}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>

      <!-- Expenses Tab -->
      <div v-show="activeTab === 'expenses'" id="tabpanel-expenses" role="tabpanel" aria-labelledby="tab-expenses">
        <Card>
          <CardHeader>
            <CardTitle>{{ t('settings_expense_categories_title') }}</CardTitle>
            <CardDescription>{{ t('settings_expense_categories_desc') }}</CardDescription>
          </CardHeader>
          <CardContent>
            <ul class="divide-y divide-[hsl(var(--border))]">
              <li
                v-for="cat in expenseCategories"
                :key="cat.id"
                class="flex items-center justify-between py-2"
              >
                <span class="text-sm">{{ cat.name }}</span>
                <Button
                  variant="ghost"
                  size="icon"
                  :aria-label="t('delete') + ' ' + cat.name"
                  class="h-8 w-8 text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--destructive))]"
                  @click="confirmRemoveCategory(cat)"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </li>
            </ul>
            <div class="mt-4 flex gap-2">
              <FormInput
                id="new_category"
                v-model="newCategoryName"
                :placeholder="t('new_category_placeholder')"
                class="flex-1"
                @keydown.enter.prevent="addCategory"
              />
              <Button :disabled="addingCategory || !newCategoryName.trim()" @click="addCategory">
                <Plus class="mr-1 h-4 w-4" />
                {{ t('add') }}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <ConfirmDialog
      :open="!!categoryToDelete"
      :title="t('delete')"
      :message="t('are_you_sure')"
      :confirm-label="t('delete')"
      @confirm="removeCategory"
      @cancel="categoryToDelete = null"
    />
  </AppLayout>
</template>
