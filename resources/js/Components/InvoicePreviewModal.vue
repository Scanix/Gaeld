<script setup>
import Modal from '@/Components/UI/Modal.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { computed } from 'vue'

const props = defineProps({
  open: Boolean,
  form: Object,
  customers: { type: Array, default: () => [] },
  vatRates: { type: Array, default: () => [] },
})

const emit = defineEmits(['close'])
const { t } = useTranslations()
const { formatCurrency, formatDate } = useFormatters()

const customer = computed(() =>
  props.customers.find(c => String(c.id) === String(props.form.customer_id))
)

const vatRateMap = computed(() => {
  const map = {}
  for (const v of props.vatRates) {
    map[v.id] = { name: v.name, rate: parseFloat(v.rate) || 0 }
  }
  return map
})

const itemSubtotal = computed(() =>
  props.form.lines.reduce((sum, line) => {
    if (line.type !== 'item') return sum
    return sum + (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0)
  }, 0)
)

const lineDetails = computed(() =>
  props.form.lines.map(line => {
    const qty = parseFloat(line.quantity) || 0
    const price = parseFloat(line.unit_price) || 0
    let rawAmount
    if (line.type === 'discount' && line.discount_type === 'percentage') {
      rawAmount = itemSubtotal.value * price / 100
    } else {
      rawAmount = qty * price
    }
    const amount = line.type === 'discount' ? -rawAmount : rawAmount
    const vat = line.vat_rate_id ? vatRateMap.value[line.vat_rate_id] : null
    const vatAmount = vat ? amount * vat.rate / 100 : 0
    return { ...line, amount, vat, vatAmount }
  })
)

const subtotal = computed(() => lineDetails.value.reduce((s, l) => s + l.amount, 0))
const vatTotal = computed(() => lineDetails.value.reduce((s, l) => s + l.vatAmount, 0))
const total = computed(() => subtotal.value + vatTotal.value)
</script>

<template>
  <Modal :open="open" :title="t('invoice_preview')" @close="$emit('close')">
    <div class="space-y-4 text-sm">
      <!-- Header -->
      <div class="flex justify-between">
        <div>
          <p class="font-semibold">{{ t('invoice') }} {{ form.number || '—' }}</p>
          <p class="text-[hsl(var(--muted-foreground))]">
            {{ form.issue_date ? formatDate(form.issue_date) : '—' }}
          </p>
        </div>
        <div class="text-right">
          <p class="font-medium">{{ customer?.name || t('no_customer') }}</p>
          <p v-if="customer?.email" class="text-[hsl(var(--muted-foreground))]">{{ customer.email }}</p>
        </div>
      </div>

      <!-- Line items -->
      <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b border-[hsl(var(--border))] text-xs text-[hsl(var(--muted-foreground))]">
            <th class="pb-2 pr-3">{{ t('description') }}</th>
            <th class="pb-2 px-3 text-right">{{ t('qty') }}</th>
            <th class="pb-2 px-3 text-right">{{ t('unit_price') }}</th>
            <th class="pb-2 px-3 text-right">{{ t('vat') }}</th>
            <th class="pb-2 pl-3 text-right">{{ t('amount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(line, i) in lineDetails" :key="i" class="border-b border-[hsl(var(--border))]">
            <td class="py-2 pr-3">{{ line.description || '—' }}</td>
            <td class="py-2 px-3 text-right tabular-nums">
              <template v-if="line.type === 'discount' && line.discount_type === 'percentage'">—</template>
              <template v-else>{{ line.quantity }}</template>
            </td>
            <td class="py-2 px-3 text-right tabular-nums">
              <template v-if="line.type === 'discount' && line.discount_type === 'percentage'">{{ line.unit_price }}%</template>
              <template v-else>{{ formatCurrency(line.unit_price, form.currency) }}</template>
            </td>
            <td class="py-2 px-3 text-right">{{ line.vat ? `${line.vat.rate}%` : '—' }}</td>
            <td class="py-2 pl-3 text-right tabular-nums">{{ formatCurrency(line.amount, form.currency) }}</td>
          </tr>
        </tbody>
      </table>
      </div>

      <!-- Totals -->
      <div class="space-y-1 border-t border-[hsl(var(--border))] pt-3">
        <div class="flex justify-between text-[hsl(var(--muted-foreground))]">
          <span>{{ t('subtotal') }}</span>
          <span class="tabular-nums">{{ formatCurrency(subtotal, form.currency) }}</span>
        </div>
        <div class="flex justify-between text-[hsl(var(--muted-foreground))]">
          <span>{{ t('vat_total') }}</span>
          <span class="tabular-nums">{{ formatCurrency(vatTotal, form.currency) }}</span>
        </div>
        <div class="flex justify-between font-semibold text-base">
          <span>{{ t('total') }}</span>
          <span class="tabular-nums">{{ formatCurrency(total, form.currency) }}</span>
        </div>
      </div>

      <!-- Notes & payment terms -->
      <div v-if="form.notes || form.payment_terms" class="space-y-2 border-t border-[hsl(var(--border))] pt-3">
        <div v-if="form.payment_terms">
          <span class="text-[hsl(var(--muted-foreground))]">{{ t('payment_terms_days') }}:</span>
          {{ form.payment_terms }} {{ t('days') }}
        </div>
        <div v-if="form.notes">
          <span class="text-[hsl(var(--muted-foreground))]">{{ t('notes') }}:</span>
          <p class="whitespace-pre-line">{{ form.notes }}</p>
        </div>
      </div>
    </div>
  </Modal>
</template>
