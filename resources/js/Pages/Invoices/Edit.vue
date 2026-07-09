<script setup>
import { ref, reactive, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
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
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { currencyOptions } from '@/lib/contactOptions'
import { useClosedFiscalYear } from '@/lib/useClosedFiscalYear'
import ClosedYearBanner from '@/Components/UI/ClosedYearBanner.vue'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import UnsavedChangesDialog from '@/Components/UI/UnsavedChangesDialog.vue'
import { useFormValidation, z } from '@/lib/useFormValidation'
import FileUpload from '@/Components/UI/FileUpload.vue'
import InvoiceLineItems from '@/Components/Invoices/InvoiceLineItems.vue'
import { Plus } from 'lucide-vue-next'

const props = defineProps({
  invoice: Object,
  customers: { type: Array, default: () => [] },
  vatRates: { type: Array, default: () => [] },
  catalogItems: { type: Array, default: () => [] },
  justificatifUrl: { type: String, default: null },
  defaultVatRateId: { type: [String, Number], default: null },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const { isClosed: isIssueDateClosed, closedYear } = useClosedFiscalYear(() => form.issue_date)

const form = useForm({
  customer_id: props.invoice.customer_id != null ? String(props.invoice.customer_id) : '',
  number: props.invoice.number ?? '',
  issue_date: props.invoice.issue_date?.slice(0, 10) ?? '',
  due_date: props.invoice.due_date?.slice(0, 10) ?? '',
  currency: props.invoice.currency ?? 'CHF',
  notes: props.invoice.notes ?? '',
  payment_terms: props.invoice.payment_terms ?? '',
  lines: (props.invoice.lines ?? []).map(l => ({
    type: l.type ?? 'item',
    discount_type: l.discount_type ?? 'flat',
    description: l.description,
    quantity: l.quantity,
    unit_price: l.unit_price,
    vat_rate_id: l.vat_rate_id ?? '',
  })),
  justificatif: null,
})

if (form.lines.length === 0) {
  form.lines.push({ type: 'item', discount_type: 'flat', description: '', quantity: 1, unit_price: 0, vat_rate_id: props.defaultVatRateId ? String(props.defaultVatRateId) : '' })
}

function saveDraft() {
  return new Promise((resolve) => {
    form.post(`/invoices/${props.invoice.id}`, {
      forceFormData: true,
      headers: { 'X-HTTP-Method-Override': 'PUT' },
      preserveState: false,
      onSuccess: () => resolve(),
      onError: () => resolve(),
    })
  })
}

const { showDialog, handleSave, handleDiscard, handleStay, forceClear } = useUnsavedChanges(
  computed(() => form.isDirty),
  { onSave: saveDraft, fallbackUrl: `/invoices/${props.invoice.id}` },
)

const { errors: clientErrors, validate, validateField } = useFormValidation(z.object({
  issue_date: z.string().min(1, 'This field is required.'),
}))

function submit() {
  if (!validate(form.data())) return
  forceClear.value = true
  form.post(`/invoices/${props.invoice.id}`, {
    forceFormData: true,
    headers: { 'X-HTTP-Method-Override': 'PUT' },
    onError: () => { forceClear.value = false },
  })
}

function onJustificatifChange(file) {
  form.justificatif = file ?? null
}

const customerList = reactive([...props.customers])
const clientOptions = ref(customerList.map(c => ({ value: String(c.id), label: c.name })))

const showCreateCustomer = ref(false)

function onCustomerCreated(customer) {
  customerList.push(customer)
  clientOptions.value = customerList.map(c => ({ value: String(c.id), label: c.name }))
  form.customer_id = String(customer.id)
}
</script>

<template>
  <AppLayout :title="t('edit_invoice')" help-page="invoices">
    <Breadcrumb :items="[{ label: t('invoices'), href: '/invoices' }, { label: invoice.number, href: `/invoices/${invoice.id}` }, { label: t('edit') }]" class="mb-4" />

    <ClosedYearBanner v-if="isIssueDateClosed" :year="closedYear" />

    <Card class="max-w-5xl">
      <CardHeader>
        <CardTitle>{{ t('edit_invoice') }} {{ invoice.number }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Invoice Details -->
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('invoice_details') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-1">
              <div class="flex items-end gap-2">
                <SearchableSelect
                  id="customer_id"
                  v-model="form.customer_id"
                  :label="t('client')"
                  :options="clientOptions"
                  :placeholder="t('select_client')"
                  :error="form.errors.customer_id || clientErrors.customer_id"
                  class="flex-1"
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
              <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('required_to_finalize') }}</p>
            </div>
            <FormInput
              id="number"
              v-model="form.number"
              :label="t('invoice_number')"
              placeholder="INV-001"
              :error="form.errors.number || clientErrors.number"
              readonly
            />
            <FormInput
              id="issue_date"
              v-model="form.issue_date"
              type="date"
              :label="t('issue_date')"
              :error="form.errors.issue_date || clientErrors.issue_date"
              required
            />
            <FormInput
              id="due_date"
              v-model="form.due_date"
              type="date"
              :label="t('due_date')"
              :error="form.errors.due_date || clientErrors.due_date"
              :hint="t('required_to_finalize')"
            />
            <FormSelect
              id="currency"
              v-model="form.currency"
              :label="t('currency')"
              :options="currencyOptions(t)"
              :error="form.errors.currency"
            />
          </div>

          <!-- Line items -->
          <hr class="border-[hsl(var(--border))]" />
          <InvoiceLineItems
            v-model="form.lines"
            :vat-rates="vatRates"
            :catalog-items="catalogItems"
            :errors="form.errors"
            :currency="form.currency"
            :default-vat-rate-id="defaultVatRateId"
          />

          <!-- Notes & Terms -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('notes_and_terms') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormTextarea
              id="notes"
              v-model="form.notes"
              :label="t('notes')"
            />
            <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('notes_printed_hint') }}</p>
            <FormInput
              id="payment_terms"
              v-model="form.payment_terms"
              :label="t('payment_terms')"
              :placeholder="t('payment_terms_example')"
            />
          </div>

          <FileUpload
            size="compact"
            :label="t('justificatif')"
            :error="form.errors.justificatif"
            @change="onJustificatifChange"
          >
            <p v-if="justificatifUrl && !form.justificatif" class="text-xs text-[hsl(var(--muted-foreground))]">
              {{ t('justificatif_attached') }}
            </p>
          </FileUpload>

          <div class="flex flex-wrap justify-end gap-3">
            <Button as="a" :href="`/invoices/${invoice.id}`" variant="outline">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="form.processing" :loading="form.processing">{{ t('save_changes') }}</Button>
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

    <UnsavedChangesDialog
      :open="showDialog"
      :saving="form.processing"
      @save="handleSave"
      @discard="handleDiscard"
      @stay="handleStay"
    />
  </AppLayout>
</template>
