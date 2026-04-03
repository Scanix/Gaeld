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
import QuickReceiptButton from '@/Components/QuickReceiptButton.vue'
import FormFileInput from '@/Components/UI/FormFileInput.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import { Plus, HelpCircle } from 'lucide-vue-next'

const props = defineProps({
  vatRates: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
})

const form = useForm({
  category: '',
  description: '',
  amount: '',
  vat_amount: '',
  vat_rate_id: '',
  date: new Date().toISOString().slice(0, 10),
  vendor: '',
  supplier_id: '',
  currency: 'CHF',
  payment_method: '',
  receipt: null,
})

useUnsavedChanges(computed(() => form.isDirty))

function submit() {
  form.post('/expenses', { forceFormData: true })
}

function onReceiptChange(e) {
  form.receipt = e.target.files[0] ?? null
}

const { t } = useTranslations()

const categoryOptions = props.categories.map(c => ({ value: c.name, label: c.name }))

const vatOptions = [
  { value: '', label: t('no_vat') },
  ...props.vatRates.map(v => ({ value: v.id, label: `${v.name} (${v.rate}%)` })),
]

const paymentMethodOptions = [
  { value: '', label: '—' },
  { value: 'cash', label: t('payment_cash') },
  { value: 'card', label: t('payment_card') },
  { value: 'bank_transfer', label: t('payment_bank_transfer') },
  { value: 'other', label: t('payment_other') },
]

const supplierList = reactive([...props.suppliers])
const supplierOptions = ref([
  { value: '', label: '—' },
  ...supplierList.map(s => ({ value: s.id, label: s.name })),
])

const showCreateSupplier = ref(false)

watch(() => form.supplier_id, (id) => {
  const supplier = supplierList.find(s => s.id === id)
  form.vendor = supplier?.name ?? ''
})

function onSupplierCreated(supplier) {
  supplierList.push(supplier)
  supplierOptions.value = [
    { value: '', label: '—' },
    ...supplierList.map(s => ({ value: s.id, label: s.name })),
  ]
  form.supplier_id = supplier.id
  form.vendor = supplier.name
}
</script>

<template>
  <AppLayout :title="t('create_expense')" help-page="expenses">
    <Breadcrumb :items="[{ label: t('expenses'), href: '/expenses' }, { label: t('create_expense') }]" class="mb-4" />

    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('new_expense') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Expense Details -->
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('expense_details') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex items-end gap-2">
              <FormSelect
                id="supplier_id"
                v-model="form.supplier_id"
                :label="t('vendor')"
                :options="supplierOptions"
                :placeholder="t('select_supplier')"
                :error="form.errors.supplier_id"
                class="flex-1"
              />
              <Button
                type="button"
                variant="outline"
                size="icon"
                class="mb-[2px] shrink-0"
                :title="t('new_supplier')"
                @click="showCreateSupplier = true"
              >
                <Plus class="h-4 w-4" />
              </Button>
            </div>
            <FormInput
              id="amount"
              v-model="form.amount"
              type="number"
              :label="t('amount')"
              :error="form.errors.amount"
              required
            />
            <FormInput
              id="date"
              v-model="form.date"
              type="date"
              :label="t('date')"
              :error="form.errors.date"
              required
            />
            <FormSelect
              id="payment_method"
              v-model="form.payment_method"
              :label="t('payment_method')"
              :options="paymentMethodOptions"
              :error="form.errors.payment_method"
            />
          </div>

          <!-- Categorization & VAT -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('categorization_vat') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="relative">
              <FormSelect
                id="category"
                v-model="form.category"
                :label="t('category')"
                :options="categoryOptions"
                :placeholder="t('select_category')"
                :error="form.errors.category"
                required
              />
              <Tooltip :content="t('tooltip_expense_category')" side="top" class="absolute right-0 top-0">
                <HelpCircle class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))]" />
              </Tooltip>
            </div>
            <div class="relative">
              <FormSelect
                id="vat_rate_id"
                v-model="form.vat_rate_id"
                :label="t('vat_rate')"
                :options="vatOptions"
              />
              <Tooltip :content="t('tooltip_vat_rate')" side="top" class="absolute right-0 top-0">
                <HelpCircle class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))]" />
              </Tooltip>
            </div>
            <FormInput
              id="vat_amount"
              v-model="form.vat_amount"
              type="number"
              :label="t('vat_amount')"
              :error="form.errors.vat_amount"
            />
          </div>

          <FormTextarea
            id="description"
            v-model="form.description"
            :label="t('description')"
          />

          <!-- Attachment -->
          <hr class="border-[hsl(var(--border))]" />

          <FormFileInput
            id="receipt"
            :label="t('receipt')"
            :error="form.errors.receipt"
            @change="onReceiptChange"
          />

          <div class="flex flex-wrap justify-end gap-3">
            <Button as="a" href="/expenses" variant="outline">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="form.processing">{{ t('create_expense') }}</Button>
          </div>
        </form>
      </CardContent>
    </Card>

    <QuickCreateContactModal
      :open="showCreateSupplier"
      contact-type="supplier"
      @close="showCreateSupplier = false"
      @created="onSupplierCreated"
    />

    <QuickReceiptButton />
  </AppLayout>
</template>
