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
import FormSelect from '@/Components/UI/FormSelect.vue'
import QuickCreateContactModal from '@/Components/QuickCreateContactModal.vue'
import QuickReceiptButton from '@/Components/QuickReceiptButton.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useUnsavedChanges } from '@/lib/useUnsavedChanges'
import { Plus, HelpCircle } from 'lucide-vue-next'

const props = defineProps({
  vatRates: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
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

const categoryOptions = [
  { value: 'Office Supplies', label: t('cat_office_supplies') },
  { value: 'Travel', label: t('cat_travel') },
  { value: 'Software', label: t('cat_software') },
  { value: 'Professional Services', label: t('cat_professional_services') },
  { value: 'Marketing', label: t('cat_marketing') },
  { value: 'Rent', label: t('cat_rent') },
  { value: 'Utilities', label: t('cat_utilities') },
  { value: 'Insurance', label: t('cat_insurance') },
  { value: 'Other', label: t('cat_other') },
]

const vatOptions = [
  { value: '', label: t('no_vat') },
  ...props.vatRates.map(v => ({ value: v.id, label: `${v.name} (${v.rate}%)` })),
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
    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('new_expense') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-4" @submit.prevent="submit">
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

          <div>
            <label for="description" class="mb-1 block text-sm font-medium">{{ t('description') }}</label>
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              class="flex w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))]"
            />
          </div>

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
          </div>

          <div class="flex justify-end gap-3">
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
