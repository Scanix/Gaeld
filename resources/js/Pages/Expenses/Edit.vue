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
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import QuickCreateContactModal from '@/Components/QuickCreateContactModal.vue'
import QuickReceiptButton from '@/Components/QuickReceiptButton.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import { Plus, FileText } from 'lucide-vue-next'

const props = defineProps({
  expense: Object,
  vatRates: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  receiptUrl: { type: String, default: null },
})

const form = useForm({
  category: props.expense.category ?? '',
  description: props.expense.description ?? '',
  amount: props.expense.amount ?? '',
  vat_amount: props.expense.vat_amount ?? '',
  vat_rate_id: props.expense.vat_rate_id ?? '',
  date: props.expense.date?.slice(0, 10) ?? '',
  vendor: props.expense.vendor ?? '',
  supplier_id: props.expense.supplier_id ?? '',
  currency: props.expense.currency ?? 'CHF',
  payment_method: props.expense.payment_method ?? '',
  receipt: null,
})

useUnsavedChanges(computed(() => form.isDirty))

function submit() {
  form.post(`/expenses/${props.expense.id}`, {
    forceFormData: true,
    headers: { 'X-HTTP-Method-Override': 'PUT' },
  })
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

function onSupplierCreated(supplier) {
  supplierList.push(supplier)
  supplierOptions.value = [
    { value: '', label: '—' },
    ...supplierList.map(s => ({ value: s.id, label: s.name })),
  ]
  form.supplier_id = supplier.id
  form.vendor = supplier.name
}

function onReceiptChange(e) {
  form.receipt = e.target.files[0] ?? null
}

const isImage = computed(() => {
  if (!props.receiptUrl) return false
  return /\.(jpe?g|png|gif|webp)$/i.test(props.receiptUrl)
})
</script>

<template>
  <AppLayout :title="t('edit_expense')" help-page="expenses">
    <Breadcrumb :items="[{ label: t('expenses'), href: '/expenses' }, { label: t('edit_expense') }]" class="mb-4" />

    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('edit_expense') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormSelect
              id="category"
              v-model="form.category"
              :label="t('category')"
              :options="categoryOptions"
              :placeholder="t('select_category')"
              :error="form.errors.category"
              required
            />
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
              id="vat_rate_id"
              v-model="form.vat_rate_id"
              :label="t('vat_rate')"
              :options="vatOptions"
            />
            <FormInput
              id="vat_amount"
              v-model="form.vat_amount"
              type="number"
              :label="t('vat_amount')"
              :error="form.errors.vat_amount"
            />
            <FormSelect
              id="payment_method"
              v-model="form.payment_method"
              :label="t('payment_method')"
              :options="paymentMethodOptions"
              :error="form.errors.payment_method"
            />
          </div>

          <FormTextarea
            id="description"
            v-model="form.description"
            :label="t('description')"
          />

          <div>
            <label for="receipt" class="mb-1 block text-sm font-medium">{{ t('receipt') }}</label>
            <input
              id="receipt"
              type="file"
              accept=".pdf,.jpg,.jpeg,.png"
              class="block w-full text-sm text-[hsl(var(--muted-foreground))] file:mr-4 file:rounded-md file:border-0 file:bg-[hsl(var(--primary))] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[hsl(var(--primary-foreground))] hover:file:opacity-90"
              @change="onReceiptChange"
            />
            <p v-if="form.errors.receipt" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.receipt }}</p>
            <!-- Receipt preview -->
            <div v-if="receiptUrl && !form.receipt" class="mt-3">
              <p class="mb-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('receipt_preview') }}</p>
              <a :href="receiptUrl" target="_blank" rel="noopener">
                <img
                  v-if="isImage"
                  :src="receiptUrl"
                  :alt="t('receipt_preview')"
                  class="h-40 rounded-md border border-[hsl(var(--border))] object-contain"
                />
                <div
                  v-else
                  class="inline-flex items-center gap-2 rounded-md border border-[hsl(var(--border))] px-3 py-2 text-sm hover:bg-[hsl(var(--accent))]"
                >
                  <FileText class="h-5 w-5 text-[hsl(var(--muted-foreground))]" />
                  {{ t('receipt_attached') }}
                </div>
              </a>
            </div>
          </div>

          <div class="flex justify-end gap-3">
            <Button as="a" :href="`/expenses/${expense.id}`" variant="outline">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="form.processing">{{ t('save_changes') }}</Button>
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
