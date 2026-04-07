<script setup>
import { ref, reactive, computed, watch } from 'vue'
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
import FormFileInput from '@/Components/UI/FormFileInput.vue'
import { Plus, Trash2, HelpCircle } from 'lucide-vue-next'
import Tooltip from '@/Components/UI/Tooltip.vue'

const props = defineProps({
  customers: { type: Array, default: () => [] },
  vatRates: { type: Array, default: () => [] },
  suggestedNumber: { type: String, default: '' },
  defaultNotes: { type: String, default: '' },
  defaultPaymentTermsDays: { type: Number, default: null },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const { isClosed: isIssueDateClosed, closedYear } = useClosedFiscalYear(() => form.issue_date)

const form = useForm({
  customer_id: '',
  number: props.suggestedNumber,
  issue_date: new Date().toISOString().slice(0, 10),
  due_date: '',
  currency: 'CHF',
  notes: props.defaultNotes,
  payment_terms: '',
  lines: [{ type: 'item', discount_type: 'flat', description: '', quantity: 1, unit_price: 0, vat_rate_id: '' }],
  justificatif: null,
  finalize: false,
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
  number: z.string().min(1, 'This field is required.').max(50, 'Must be at most 50 characters.'),
  issue_date: z.string().min(1, 'This field is required.'),
  due_date: z.string().min(1, 'This field is required.'),
}))

const draftValidation = useFormValidation(z.object({
  number: z.string().min(1, 'This field is required.').max(50, 'Must be at most 50 characters.'),
  issue_date: z.string().min(1, 'This field is required.'),
}))

function addLine(type = 'item') {
  form.lines.push({ type, discount_type: 'flat', description: '', quantity: 1, unit_price: 0, vat_rate_id: '' })
}

function removeLine(index) {
  if (form.lines.length > 1) {
    form.lines.splice(index, 1)
  }
}

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

// I1: Live running totals
const vatRateMap = computed(() => {
  const map = {}
  for (const v of props.vatRates) {
    map[v.id] = parseFloat(v.rate) || 0
  }
  return map
})

const itemSubtotal = computed(() =>
  form.lines.reduce((sum, l) => {
    if (l.type !== 'item') return sum
    return sum + (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)
  }, 0)
)

const subtotal = computed(() =>
  form.lines.reduce((sum, l) => {
    if (l.type === 'text') return sum
    if (l.type === 'discount') {
      if (l.discount_type === 'percentage') {
        return sum - itemSubtotal.value * (parseFloat(l.unit_price) || 0) / 100
      }
      return sum - (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)
    }
    return sum + (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)
  }, 0)
)

const vatTotal = computed(() =>
  form.lines.reduce((sum, l) => {
    if (l.type === 'text') return sum
    const rate = l.vat_rate_id ? (vatRateMap.value[l.vat_rate_id] || 0) : 0
    let lineAmount
    if (l.type === 'discount' && l.discount_type === 'percentage') {
      lineAmount = itemSubtotal.value * (parseFloat(l.unit_price) || 0) / 100
    } else {
      lineAmount = (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)
    }
    const vatAmount = lineAmount * rate / 100
    return sum + (l.type === 'discount' ? -vatAmount : vatAmount)
  }, 0)
)

const total = computed(() => subtotal.value + vatTotal.value)

function onJustificatifChange(e) {
  form.justificatif = e.target.files[0] ?? null
}

const customerList = reactive([...props.customers])
const clientOptions = ref(customerList.map(c => ({ value: c.id, label: c.name })))

const showCreateCustomer = ref(false)
const showPreview = ref(false)

function onCustomerCreated(customer) {
  customerList.push(customer)
  clientOptions.value = customerList.map(c => ({ value: c.id, label: c.name }))
  form.customer_id = customer.id
}

const vatOptions = [
  { value: '', label: t('no_vat') },
  ...props.vatRates.map(v => ({ value: v.id, label: `${v.name} (${v.rate}%)` })),
]

const lineTypeOptions = [
  { value: 'item', label: t('line_type_item') },
  { value: 'discount', label: t('line_type_discount') },
  { value: 'text', label: t('line_type_text') },
]

const discountTypeOptions = [
  { value: 'flat', label: t('discount_flat') },
  { value: 'percentage', label: t('discount_percentage') },
]

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
  const customer = customerList.find(c => c.id === form.customer_id)
  const days = customer?.payment_terms || props.defaultPaymentTermsDays
  if (days) {
    form.payment_terms = String(days)
    form.due_date = computeDueDate(form.issue_date, days)
  }
}

watch(() => form.customer_id, applyPaymentTerms)
watch(() => form.issue_date, applyPaymentTerms)

function onDueDateManualEdit() {
  dueDateManuallyEdited.value = true
  validateField('due_date', form.due_date)
}
</script>

<template>
  <AppLayout :title="t('create_invoice')" help-page="invoices">
    <Breadcrumb :items="[{ label: t('invoices'), href: '/invoices' }, { label: t('create_invoice') }]" class="mb-4" />

    <ClosedYearBanner v-if="isIssueDateClosed" :year="closedYear" />

    <Card class="max-w-5xl">
      <CardHeader>
        <CardTitle>{{ t('new_invoice') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Invoice Details -->
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('invoice_details') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex items-end gap-2">
              <FormSelect
                id="customer_id"
                v-model="form.customer_id"
                :label="t('client')"
                :options="clientOptions"
                :placeholder="t('select_client')"
                :error="form.errors.customer_id || clientErrors.customer_id"
                required
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
            <FormInput
              id="number"
              v-model="form.number"
              :label="t('invoice_number')"
              :placeholder="t('invoice_number_placeholder')"
              :error="form.errors.number || clientErrors.number"
              required
              @blur="validateField('number', form.number)"
            />
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
              required
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

          <!-- Line items -->
          <hr class="border-[hsl(var(--border))]" />
          <div>
            <h3 class="mb-3 text-sm font-medium">{{ t('line_items') }}</h3>
            <div class="space-y-3">
              <div
                v-for="(line, i) in form.lines"
                :key="i"
                class="grid grid-cols-1 gap-3 rounded-lg border border-[hsl(var(--border))] p-3 sm:grid-cols-12 sm:items-end sm:gap-2"
              >
                <div class="sm:col-span-2">
                  <FormSelect
                    :id="`line-type-${i}`"
                    v-model="line.type"
                    :label="t('type')"
                    :options="lineTypeOptions"
                  />
                </div>
                <div :class="line.type === 'text' ? 'sm:col-span-9' : 'sm:col-span-3'">
                  <FormInput
                    :id="`line-desc-${i}`"
                    v-model="line.description"
                    :label="t('description')"
                    :error="form.errors[`lines.${i}.description`]"
                    required
                  />
                </div>
                <template v-if="line.type !== 'text'">
                  <div class="grid grid-cols-2 gap-3 sm:contents">
                    <div v-if="line.type !== 'discount' || line.discount_type !== 'percentage'" class="sm:col-span-2">
                      <FormInput
                        :id="`line-qty-${i}`"
                        v-model="line.quantity"
                        type="number"
                        :label="t('qty')"
                        :error="form.errors[`lines.${i}.quantity`]"
                        required
                      />
                    </div>
                    <div :class="line.type === 'discount' && line.discount_type === 'percentage' ? 'sm:col-span-2' : 'sm:col-span-2'">
                      <FormInput
                        :id="`line-price-${i}`"
                        v-model="line.unit_price"
                        type="number"
                        :label="line.type === 'discount' ? (line.discount_type === 'percentage' ? t('discount_percentage') : t('line_type_discount')) : t('unit_price')"
                        :error="form.errors[`lines.${i}.unit_price`]"
                        required
                      />
                    </div>
                    <div v-if="line.type === 'discount'" class="sm:col-span-2">
                      <FormSelect
                        :id="`line-discount-type-${i}`"
                        v-model="line.discount_type"
                        :label="t('discount_mode')"
                        :options="discountTypeOptions"
                      />
                    </div>
                  </div>
                  <div class="flex items-end gap-3 sm:contents">
                    <div class="flex-1 sm:col-span-2 relative">
                      <FormSelect
                        :id="`line-vat-${i}`"
                        v-model="line.vat_rate_id"
                        :label="t('vat')"
                        :options="vatOptions"
                      />
                      <div class="absolute right-0 top-0">
                        <Tooltip :content="t('tooltip_vat_rate')" side="top">
                          <HelpCircle class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))]" />
                        </Tooltip>
                      </div>
                    </div>
                  </div>
                </template>
                <div class="sm:col-span-1 flex justify-end pb-2">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :disabled="form.lines.length <= 1"
                    @click="removeLine(i)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
              <Button type="button" variant="outline" size="sm" @click="addLine('item')">
                <Plus class="mr-1 h-4 w-4" />
                {{ t('add_line') }}
              </Button>
              <Button type="button" variant="outline" size="sm" @click="addLine('discount')">
                <Plus class="mr-1 h-4 w-4" />
                {{ t('add_discount_line') }}
              </Button>
              <Button type="button" variant="outline" size="sm" @click="addLine('text')">
                <Plus class="mr-1 h-4 w-4" />
                {{ t('add_text_line') }}
              </Button>
            </div>

            <!-- Running totals -->
            <div class="mt-4 space-y-1 border-t pt-3 text-sm">
              <div class="flex justify-between text-[hsl(var(--muted-foreground))]">
                <span>{{ t('subtotal') }}</span>
                <span class="tabular-nums">{{ formatCurrency(subtotal, form.currency) }}</span>
              </div>
              <div class="flex justify-between text-[hsl(var(--muted-foreground))]">
                <span>{{ t('vat_total') }}</span>
                <span class="tabular-nums">{{ formatCurrency(vatTotal, form.currency) }}</span>
              </div>
              <div class="flex justify-between font-semibold">
                <span>{{ t('total') }}</span>
                <span class="tabular-nums">{{ formatCurrency(total, form.currency) }}</span>
              </div>
            </div>
          </div>

          <!-- Notes & Terms -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('notes_and_terms') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

          <FormFileInput
            id="justificatif"
            :label="t('justificatif')"
            :error="form.errors.justificatif"
            @change="onJustificatifChange"
          />

          <div class="flex flex-wrap justify-end gap-3">
            <Button as="a" href="/invoices" variant="outline">{{ t('cancel') }}</Button>
            <Button type="button" variant="outline" @click="showPreview = true">
              {{ t('invoice_preview') }}
            </Button>
            <Button type="button" variant="outline" :disabled="form.processing || isIssueDateClosed" :title="isIssueDateClosed ? t('fiscal_year_closed_action_disabled') : undefined" @click="submitAndFinalize">
              {{ t('create_and_finalize') }}
            </Button>
            <Button type="submit" :disabled="form.processing">{{ t('create_invoice') }}</Button>
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
