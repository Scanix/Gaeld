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
import { Plus, Trash2, HelpCircle } from 'lucide-vue-next'
import Tooltip from '@/Components/UI/Tooltip.vue'

const props = defineProps({
  invoice: Object,
  customers: { type: Array, default: () => [] },
  vatRates: { type: Array, default: () => [] },
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
  customer_id: z.string().min(1, 'This field is required.'),
  number: z.string().min(1, 'This field is required.').max(50, 'Must be at most 50 characters.'),
  issue_date: z.string().min(1, 'This field is required.'),
  due_date: z.string().min(1, 'This field is required.'),
}))

function addLine(type = 'item') {
  form.lines.push({ type, discount_type: 'flat', description: '', quantity: 1, unit_price: 0, vat_rate_id: type === 'item' && props.defaultVatRateId ? String(props.defaultVatRateId) : '' })
}

function removeLine(index) {
  if (form.lines.length > 1) {
    form.lines.splice(index, 1)
  }
}

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
            <div class="flex items-end gap-2">
              <SearchableSelect
                id="customer_id"
                v-model="form.customer_id"
                :label="t('client')"
                :options="clientOptions"
                :placeholder="t('select_client')"
                :error="form.errors.customer_id || clientErrors.customer_id"
                required
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
            <FormInput
              id="number"
              v-model="form.number"
              :label="t('invoice_number')"
              placeholder="INV-001"
              :error="form.errors.number || clientErrors.number"
              required
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
              required
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
                  <FormTextarea
                    :id="`line-desc-${i}`"
                    v-model="line.description"
                    :label="t('description')"
                    :error="form.errors[`lines.${i}.description`]"
                    :rows="2"
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
