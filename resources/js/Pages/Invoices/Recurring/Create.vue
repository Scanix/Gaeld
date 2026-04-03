<script setup>
import { ref, computed } from 'vue'
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
import { useTranslations } from '@/lib/useTranslations'
import { currencyOptions } from '@/lib/contactOptions'
import { Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  customers: { type: Array, default: () => [] },
  vatRates: { type: Array, default: () => [] },
})

const { t } = useTranslations()

const form = useForm({
  customer_id: '',
  frequency: 'monthly',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: '',
  currency: 'CHF',
  notes: '',
  payment_terms: '',
  lines: [{ description: '', quantity: 1, unit_price: 0, vat_rate_id: '' }],
})

function addLine() {
  form.lines.push({ description: '', quantity: 1, unit_price: 0, vat_rate_id: '' })
}

function removeLine(index) {
  if (form.lines.length > 1) {
    form.lines.splice(index, 1)
  }
}

function submit() {
  form
    .transform((data) => ({
      customer_id: data.customer_id,
      frequency: data.frequency,
      next_issue_date: data.start_date,
      end_date: data.end_date || null,
      template_data: {
        lines: data.lines,
        notes: data.notes,
        currency: data.currency,
        payment_terms: data.payment_terms,
      },
    }))
    .post('/invoices/recurring')
}

const clientOptions = computed(() =>
  props.customers.map(c => ({ value: c.id, label: c.name }))
)

const frequencyOptions = computed(() => [
  { value: 'weekly', label: t('frequency_weekly') },
  { value: 'monthly', label: t('frequency_monthly') },
  { value: 'quarterly', label: t('frequency_quarterly') },
  { value: 'yearly', label: t('frequency_yearly') },
])

const vatOptions = computed(() => [
  { value: '', label: t('no_vat') },
  ...props.vatRates.map(v => ({ value: v.id, label: `${v.name} (${v.rate}%)` })),
])
</script>

<template>
  <AppLayout :title="t('create_recurring_invoice')">
    <Breadcrumb :items="[
      { label: t('invoices'), href: '/invoices' },
      { label: t('recurring'), href: '/invoices/recurring' },
      { label: t('create') },
    ]" class="mb-4" />

    <Card class="max-w-3xl">
      <CardHeader>
        <CardTitle>{{ t('new_recurring_invoice') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormSelect
              id="customer_id"
              v-model="form.customer_id"
              :label="t('client')"
              :options="clientOptions"
              :placeholder="t('select_client')"
              :error="form.errors.customer_id"
              required
            />
            <FormSelect
              id="frequency"
              v-model="form.frequency"
              :label="t('frequency')"
              :options="frequencyOptions"
              :error="form.errors.frequency"
              required
            />
            <FormInput
              id="start_date"
              v-model="form.start_date"
              type="date"
              :label="t('start_date')"
              :error="form.errors.start_date"
              required
            />
            <FormInput
              id="end_date"
              v-model="form.end_date"
              type="date"
              :label="t('end_date')"
              :error="form.errors.end_date"
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
          <div>
            <h3 class="mb-3 text-sm font-medium">{{ t('line_items') }}</h3>
            <div class="space-y-3">
              <div
                v-for="(line, i) in form.lines"
                :key="i"
                class="grid grid-cols-1 gap-3 rounded-lg border border-[hsl(var(--border))] p-3 sm:grid-cols-12 sm:items-end sm:gap-2"
              >
                <div class="sm:col-span-4">
                  <FormInput
                    :id="`line-desc-${i}`"
                    v-model="line.description"
                    :label="t('description')"
                    :error="form.errors[`lines.${i}.description`]"
                    required
                  />
                </div>
                <div class="grid grid-cols-2 gap-3 sm:contents">
                  <div class="sm:col-span-2">
                    <FormInput
                      :id="`line-qty-${i}`"
                      v-model="line.quantity"
                      type="number"
                      :label="t('qty')"
                      :error="form.errors[`lines.${i}.quantity`]"
                      required
                    />
                  </div>
                  <div class="sm:col-span-2">
                    <FormInput
                      :id="`line-price-${i}`"
                      v-model="line.unit_price"
                      type="number"
                      :label="t('unit_price')"
                      :error="form.errors[`lines.${i}.unit_price`]"
                      required
                    />
                  </div>
                </div>
                <div class="flex items-end gap-3 sm:contents">
                  <div class="flex-1 sm:col-span-3">
                    <FormSelect
                      :id="`line-vat-${i}`"
                      v-model="line.vat_rate_id"
                      :label="t('vat')"
                      :options="vatOptions"
                    />
                  </div>
                  <div class="flex justify-end pb-2 sm:col-span-1">
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
            </div>

            <Button type="button" variant="outline" size="sm" class="mt-3" @click="addLine">
              <Plus class="mr-1 h-4 w-4" />
              {{ t('add_line') }}
            </Button>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormTextarea
              id="notes"
              v-model="form.notes"
              :label="t('notes')"
            />
            <FormInput
              id="payment_terms"
              v-model="form.payment_terms"
              :label="t('payment_terms')"
              placeholder="Net 30"
            />
          </div>

          <div class="flex flex-wrap justify-end gap-3">
            <Button as="a" href="/invoices/recurring" variant="outline">{{ t('cancel') }}</Button>
            <Button type="submit" :disabled="form.processing">{{ t('create_recurring_invoice') }}</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
