<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormTextarea from '@/Components/UI/FormTextarea.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import QuickCreateContactModal from '@/Components/QuickCreateContactModal.vue'
import InvoicePreviewModal from '@/Components/InvoicePreviewModal.vue'
import { currencyOptions } from '@/lib/contactOptions'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { useClosedFiscalYear } from '@/lib/useClosedFiscalYear'
import ClosedYearBanner from '@/Components/UI/ClosedYearBanner.vue'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import UnsavedChangesDialog from '@/Components/UI/UnsavedChangesDialog.vue'
import { useFormValidation, z } from '@/lib/useFormValidation'
import FileUpload from '@/Components/UI/FileUpload.vue'
import InvoiceLineItems from '@/Components/Invoices/InvoiceLineItems.vue'
import { Plus } from 'lucide-vue-next'

const props = defineProps({
  customers: { type: Array, default: () => [] },
  vatRates: { type: Array, default: () => [] },
  catalogItems: { type: Array, default: () => [] },
  suggestedNumber: { type: String, default: '' },
  defaultNotes: { type: String, default: '' },
  defaultPaymentTermsDays: { type: Number, default: null },
  defaultVatRateId: { type: [String, Number], default: null },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const { isClosed: isIssueDateClosed, closedYear } = useClosedFiscalYear(() => form.issue_date)

const form = useForm({
  customer_id: '',
  issue_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  currency: 'CHF',
  notes: props.defaultNotes,
  payment_terms: '',
  lines: [{ type: 'item', discount_type: 'flat', description: '', quantity: 1, unit_price: 0, vat_rate_id: props.defaultVatRateId ? String(props.defaultVatRateId) : '' }],
  justificatif: null,
  finalize: false,
})

// Reload the suggested-number preview whenever the issue-date year changes so
// that back-dated invoices display the correct year-scoped sequence number.
let lastReloadedYear = new Date(form.issue_date).getFullYear()
watch(() => form.issue_date, (val) => {
  if (!val) return
  const y = new Date(val).getFullYear()
  if (Number.isNaN(y) || y === lastReloadedYear) return
  lastReloadedYear = y
  router.reload({
    only: ['suggestedNumber'],
    data: { for_year: y },
    preserveState: true,
  })
})

function saveDraft() {
  return new Promise((resolve) => {
    form.finalize = false
    form.post('/invoices', {
      forceFormData: true,
      preserveState: false,
      onSuccess: () => resolve(),
      onError: () => resolve(),
    })
  })
}

const { showDialog, handleSave, handleDiscard, handleStay, forceClear } = useUnsavedChanges(
  computed(() => form.isDirty),
  { onSave: saveDraft, fallbackUrl: '/invoices' },
)

const { errors: clientErrors, validate, validateField } = useFormValidation(z.object({
  customer_id: z.string().min(1, 'This field is required.'),
  issue_date: z.string().min(1, 'This field is required.'),
  due_date: z.string().min(1, 'This field is required.'),
}))

const draftValidation = useFormValidation(z.object({
  issue_date: z.string().min(1, 'This field is required.'),
}))

function submit() {
  if (!draftValidation.validate(form.data())) return
  forceClear.value = true
  form.finalize = false
  form.post('/invoices', {
    forceFormData: true,
    onError: () => { forceClear.value = false },
  })
}

function submitAndFinalize() {
  if (!validate(form.data())) return
  forceClear.value = true
  form.finalize = true
  form.post('/invoices', {
    forceFormData: true,
    onError: () => { forceClear.value = false },
  })
}

function onJustificatifChange(file) {
  form.justificatif = file ?? null
}

const customerList = reactive([...props.customers])
const clientOptions = ref(customerList.map(c => ({ value: String(c.id), label: c.name })))

const showCreateCustomer = ref(false)
const showPreview = ref(false)

function onCustomerCreated(customer) {
  customerList.push(customer)
  clientOptions.value = customerList.map(c => ({ value: String(c.id), label: c.name }))
  form.customer_id = String(customer.id)
}

// Due date auto-fill from customer or org default payment terms
const dueDateManuallyEdited = ref(false)

function computeDueDate(issueDate, paymentTermsDays) {
  if (!issueDate || !paymentTermsDays) return ''
  const date = new Date(issueDate)
  date.setDate(date.getDate() + parseInt(paymentTermsDays))
  return date.toISOString().slice(0, 10)
}

function applyPaymentTerms() {
  if (dueDateManuallyEdited.value) return
  const customer = customerList.find(c => String(c.id) === form.customer_id)
  const days = customer?.payment_terms || props.defaultPaymentTermsDays
  if (days) {
    form.payment_terms = String(days)
    form.due_date = computeDueDate(form.issue_date, days)
  }
}

watch(() => form.customer_id, applyPaymentTerms)
watch(() => form.issue_date, applyPaymentTerms)

// When the user manually changes payment_terms, recompute due_date too
// (unless they previously edited the due_date by hand).
watch(() => form.payment_terms, (days) => {
  if (dueDateManuallyEdited.value) return
  if (days && form.issue_date) {
    form.due_date = computeDueDate(form.issue_date, days)
  }
})

function onDueDateManualEdit() {
  dueDateManuallyEdited.value = true
  validateField('due_date', form.due_date)
}
</script>

<template>
  <AppLayout :title="t('create_invoice')" help-page="invoices">
    <Breadcrumb :items="[{ label: t('invoices'), href: '/invoices' }, { label: t('create_invoice') }]" class="mb-4" />

    <ClosedYearBanner v-if="isIssueDateClosed" :year="closedYear" />

    <Card class="max-w-6xl overflow-hidden">
      <CardHeader class="border-b bg-[hsl(var(--muted)/0.18)]">
        <CardTitle>{{ t('new_invoice') }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-8 p-4 sm:p-6 lg:p-8">
        <form class="space-y-8" @submit.prevent="submit">
          <!-- Invoice Details -->
          <section class="space-y-4" aria-labelledby="invoice-details-heading">
            <div>
              <h3 id="invoice-details-heading" class="text-base font-semibold text-[hsl(var(--foreground))]">{{ t('invoice_details') }}</h3>
              <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">{{ t('required_to_finalize') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
              <div class="flex items-end gap-2">
                <SearchableSelect
                  id="customer_id"
                  v-model="form.customer_id"
                  :label="t('client')"
                  :options="clientOptions"
                  :placeholder="t('select_client')"
                  :error="form.errors.customer_id || clientErrors.customer_id"
                  :required="form.finalize"
                  class="flex-1"
                  @blur="validateField('customer_id', form.customer_id)"
                />
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  class="mb-[2px] shrink-0"
                  :title="t('new_customer')"
                  @click="showCreateCustomer = true"
                >
                  <Plus class="h-4 w-4" />
                </Button>
              </div>
            </div>
            <div class="lg:col-span-2">
              <label class="text-sm font-medium leading-none">{{ t('invoice_number') }}</label>
              <div class="mt-2 flex h-11 w-full items-center rounded-md border border-[hsl(var(--input))] bg-[hsl(var(--muted))] px-3 py-1 text-base text-[hsl(var(--muted-foreground))] select-none sm:h-9 sm:text-sm">
                {{ suggestedNumber || '\u2026' }}
              </div>
            </div>
            <FormInput
              id="issue_date"
              v-model="form.issue_date"
              type="date"
              :label="t('issue_date')"
              :error="form.errors.issue_date || clientErrors.issue_date"
              required
              @blur="validateField('issue_date', form.issue_date)"
            />
            <FormInput
              id="due_date"
              v-model="form.due_date"
              type="date"
              :label="t('due_date')"
              :error="form.errors.due_date || clientErrors.due_date"
              :hint="t('required_to_finalize')"
              :required="form.finalize"
              @blur="onDueDateManualEdit"
              @change="onDueDateManualEdit"
            />
            <FormSelect
              id="currency"
              v-model="form.currency"
              :label="t('currency')"
              :options="currencyOptions(t)"
              :error="form.errors.currency"
            />
          </div>
          </section>

          <!-- Line items -->
          <InvoiceLineItems
            v-model="form.lines"
            :vat-rates="vatRates"
            :catalog-items="catalogItems"
            :errors="form.errors"
            :currency="form.currency"
            :default-vat-rate-id="defaultVatRateId"
          />

          <!-- Notes & Terms -->
          <section class="space-y-4" aria-labelledby="notes-terms-heading">
            <div>
              <h3 id="notes-terms-heading" class="text-base font-semibold text-[hsl(var(--foreground))]">{{ t('notes_and_terms') }}</h3>
            </div>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-[minmax(0,1.5fr)_minmax(16rem,1fr)] sm:items-start">
              <FormTextarea
                id="notes"
                v-model="form.notes"
                :label="t('notes')"
              />
              <div>
              <FormInput
                id="payment_terms"
                v-model="form.payment_terms"
                type="number"
                min="0"
                :label="t('payment_terms_days')"
                placeholder="30"
              />
              <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('payment_terms_hint') }}</p>
              </div>
            </div>
          </section>

          <section class="space-y-3" aria-labelledby="supporting-document-heading">
            <h3 id="supporting-document-heading" class="text-base font-semibold text-[hsl(var(--foreground))]">{{ t('justificatif') }}</h3>
            <FileUpload
              size="compact"
              :label="t('justificatif')"
              :error="form.errors.justificatif"
              @change="onJustificatifChange"
            />
          </section>

          <div class="flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
            <Button as="a" href="/invoices" variant="outline" class="w-full sm:w-auto">{{ t('cancel') }}</Button>
            <Button type="button" variant="outline" class="w-full sm:w-auto" @click="showPreview = true">
              {{ t('invoice_preview') }}
            </Button>
            <Button type="button" variant="outline" class="w-full sm:w-auto" :disabled="form.processing || isIssueDateClosed" :loading="form.processing && form.finalize" :title="isIssueDateClosed ? t('fiscal_year_closed_action_disabled') : undefined" @click="submitAndFinalize">
              {{ t('create_and_finalize') }}
            </Button>
            <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing" :loading="form.processing && !form.finalize">{{ t('create_invoice') }}</Button>
          </div>
        </form>
      </CardContent>
    </Card>

    <QuickCreateContactModal
      :open="showCreateCustomer"
      contact-type="customer"
      @close="showCreateCustomer = false"
      @created="onCustomerCreated"
    />

    <InvoicePreviewModal
      :open="showPreview"
      :form="form"
      :customers="customerList"
      :vat-rates="vatRates"
      @close="showPreview = false"
    />

    <UnsavedChangesDialog
      :open="showDialog"
      :saving="form.processing"
      @save="handleSave"
      @discard="handleDiscard"
      @stay="handleStay"
    />
  </AppLayout>
</template>
