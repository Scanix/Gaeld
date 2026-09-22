<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormTextarea from '@/Components/UI/FormTextarea.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { Plus, Trash2, ArrowUp, ArrowDown, Copy } from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: Array, required: true },
  vatRates: { type: Array, default: () => [] },
  catalogItems: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  currency: { type: String, default: 'CHF' },
  defaultVatRateId: { type: [String, Number], default: null },
  taxTreatment: { type: String, default: 'standard' },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

// The parent always passes a reactive array (e.g. Inertia's useForm data), so
// we mutate it in place rather than emitting update:modelValue for every change.
const lines = computed(() => props.modelValue)

watch(() => props.taxTreatment, (taxTreatment) => {
  if (taxTreatment !== 'reverse_charge') return
  lines.value.forEach(line => { line.vat_rate_id = '' })
}, { immediate: true })

function emptyLine(type = 'item') {
  return {
    type,
    discount_type: 'flat',
    description: '',
    quantity: 1,
    unit_price: 0,
    vat_rate_id: type === 'item' && props.defaultVatRateId ? String(props.defaultVatRateId) : '',
  }
}

function addLine(type = 'item') {
  lines.value.push(emptyLine(type))
}

function removeLine(index) {
  if (lines.value.length > 1) {
    lines.value.splice(index, 1)
  }
}

function duplicateLine(index) {
  lines.value.splice(index + 1, 0, { ...lines.value[index] })
}

const selectedCatalogItemId = ref('')

const catalogOptions = computed(() =>
  props.catalogItems.map(c => ({ value: String(c.id), label: c.name }))
)

function addLineFromCatalog(catalogItemId) {
  const item = props.catalogItems.find(c => String(c.id) === String(catalogItemId))
  if (!item) return
  lines.value.push({
    type: 'item',
    discount_type: 'flat',
    description: item.name + (item.description ? ` \u2013 ${item.description}` : ''),
    quantity: 1,
    unit_price: item.default_unit_price ?? 0,
    vat_rate_id: item.default_vat_rate_id ? String(item.default_vat_rate_id) : (props.defaultVatRateId ? String(props.defaultVatRateId) : ''),
  })
  // Reset so the same catalog item can be picked again for another line.
  selectedCatalogItemId.value = ''
}

watch(selectedCatalogItemId, (id) => {
  if (id) addLineFromCatalog(id)
})

function moveLine(index, direction) {
  const target = index + direction
  if (target < 0 || target >= lines.value.length) return
  const arr = lines.value
  const [moved] = arr.splice(index, 1)
  arr.splice(target, 0, moved)
}

const vatRateMap = computed(() => {
  const map = {}
  for (const v of props.vatRates) {
    map[v.id] = parseFloat(v.rate) || 0
  }
  return map
})

const vatOptions = computed(() => [
  { value: '', label: t('no_vat') },
  ...(props.taxTreatment === 'reverse_charge'
    ? []
    : props.vatRates.map(v => ({ value: v.id, label: `${v.name} (${v.rate}%)` }))),
])

const lineTypeOptions = computed(() => [
  { value: 'item', label: t('line_type_item') },
  { value: 'discount', label: t('line_type_discount') },
  { value: 'text', label: t('line_type_text') },
])

function lineTypeLabel(type) {
  return lineTypeOptions.value.find(option => option.value === type)?.label ?? type
}

const discountTypeOptions = computed(() => [
  { value: 'flat', label: t('discount_flat') },
  { value: 'percentage', label: t('discount_percentage') },
])

const itemSubtotal = computed(() =>
  lines.value.reduce((sum, l) => {
    if (l.type !== 'item') return sum
    return sum + (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_price) || 0)
  }, 0)
)

const subtotal = computed(() =>
  lines.value.reduce((sum, l) => {
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
  lines.value.reduce((sum, l) => {
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

function lineAmount(line) {
  if (line.type === 'text') return null

  if (line.type === 'discount' && line.discount_type === 'percentage') {
    return -(itemSubtotal.value * (parseFloat(line.unit_price) || 0) / 100)
  }

  const amount = (parseFloat(line.quantity) || 0) * (parseFloat(line.unit_price) || 0)

  return line.type === 'discount' ? -amount : amount
}

function formattedLineAmount(line) {
  const amount = lineAmount(line)

  return amount === null ? '—' : formatCurrency(amount, props.currency)
}

defineExpose({ subtotal, vatTotal, total })
</script>

<template>
  <div>
    <div class="mb-3 flex items-start justify-between gap-4">
      <div>
        <h3 class="text-sm font-medium">{{ t('line_items') }}</h3>
        <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('invoice_line_items_hint') }}</p>
      </div>
      <span class="shrink-0 text-xs tabular-nums text-[hsl(var(--muted-foreground))]">{{ lines.length }} {{ t('line_items') }}</span>
    </div>
    <div class="space-y-3">
      <div
        v-for="(line, i) in lines"
        :key="i"
        :class="[
          'rounded-lg border p-4 shadow-sm xl:p-5',
          line.type === 'discount' ? 'border-[hsl(var(--destructive)/0.35)] bg-[hsl(var(--destructive)/0.045)]' : line.type === 'text' ? 'border-[hsl(var(--border))] bg-[hsl(var(--muted)/0.3)]' : 'border-[hsl(var(--border))] bg-[hsl(var(--card))]',
        ]"
      >
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[hsl(var(--border))] pb-3">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-[hsl(var(--muted-foreground))]">{{ t('line_items') }} {{ i + 1 }}</span>
            <span class="rounded-full bg-[hsl(var(--muted))] px-2 py-1 text-xs font-medium text-[hsl(var(--foreground))]">{{ lineTypeLabel(line.type) }}</span>
          </div>
          <div class="flex items-center gap-1">
            <Button type="button" variant="ghost" size="icon" :disabled="i === 0" :title="t('move_line_up')" @click="moveLine(i, -1)">
              <ArrowUp class="h-4 w-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" :disabled="i === lines.length - 1" :title="t('move_line_down')" @click="moveLine(i, 1)">
              <ArrowDown class="h-4 w-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" :title="t('duplicate_line')" @click="duplicateLine(i)">
              <Copy class="h-4 w-4" />
            </Button>
            <Button type="button" variant="ghost" size="icon" :disabled="lines.length <= 1" :title="t('delete')" @click="removeLine(i)">
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(10rem,0.8fr)_minmax(0,2fr)]">
          <div>
            <FormSelect
              :id="`line-type-${i}`"
              v-model="line.type"
              :label="t('type')"
              :options="lineTypeOptions"
            />
            <FormSelect
              v-if="line.type === 'discount'"
              :id="`line-discount-type-${i}`"
              v-model="line.discount_type"
              :label="t('discount_mode')"
              :options="discountTypeOptions"
              class="mt-4"
            />
          </div>
          <div>
          <FormTextarea
            :id="`line-desc-${i}`"
            v-model="line.description"
            :label="t('description')"
            :error="errors[`lines.${i}.description`]"
            :rows="3"
            required
          />
          </div>
        </div>

        <template v-if="line.type !== 'text'">
          <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(7rem,.65fr)_minmax(11rem,1fr)_minmax(13rem,1.25fr)_minmax(10rem,.9fr)]">
            <FormInput
              :id="`line-qty-${i}`"
              v-model="line.quantity"
              type="number"
              :label="t('qty')"
              :error="errors[`lines.${i}.quantity`]"
              :readonly="line.type === 'discount' && line.discount_type === 'percentage'"
              required
            />
            <FormInput
              :id="`line-price-${i}`"
              v-model="line.unit_price"
              type="number"
              :label="line.type === 'discount' ? (line.discount_type === 'percentage' ? t('discount_percentage') : t('line_type_discount')) : t('unit_price')"
              :error="errors[`lines.${i}.unit_price`]"
              :hint="line.type === 'item' ? t('negative_price_hint') : undefined"
              required
            />
            <div>
              <FormSelect
                :id="`line-vat-${i}`"
                v-model="line.vat_rate_id"
                :label="t('vat')"
                :options="vatOptions"
              />
              <p class="mt-2 text-xs leading-4 text-[hsl(var(--muted-foreground))]">{{ t('tooltip_vat_rate') }}</p>
            </div>
            <div class="flex min-h-11 items-center justify-between rounded-md bg-[hsl(var(--muted)/0.45)] px-3 py-2 text-sm font-semibold tabular-nums sm:col-span-2 xl:col-span-1 xl:justify-end">
              <span class="text-xs font-medium text-[hsl(var(--muted-foreground))]">{{ t('amount') }}</span>
              <span :class="line.type === 'discount' ? 'text-[hsl(var(--destructive))]' : 'text-[hsl(var(--foreground))]'">{{ formattedLineAmount(line) }}</span>
            </div>
          </div>
        </template>
        <div v-else class="mt-4 flex items-center justify-between rounded-md bg-[hsl(var(--muted)/0.45)] px-3 py-3 text-sm text-[hsl(var(--muted-foreground))]">
          <span class="text-xs font-medium">{{ t('amount') }}</span>
          <span>{{ formattedLineAmount(line) }}</span>
        </div>
      </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
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
      <SearchableSelect
        v-if="catalogOptions.length > 0"
        id="add-from-catalog"
        v-model="selectedCatalogItemId"
        :options="catalogOptions"
        :placeholder="t('add_from_catalog')"
        force-searchable
        class="min-w-64 flex-1 sm:w-72 sm:flex-none"
      />
    </div>

    <!-- Running totals -->
    <div class="mt-4 space-y-2 border-t bg-[hsl(var(--muted)/0.2)] px-4 py-4 text-sm sm:ml-auto sm:max-w-sm">
      <div class="flex justify-between text-[hsl(var(--muted-foreground))]">
        <span>{{ t('subtotal') }}</span>
        <span class="tabular-nums">{{ formatCurrency(subtotal, currency) }}</span>
      </div>
      <div class="flex justify-between text-[hsl(var(--muted-foreground))]">
        <span>{{ t('vat_total') }}</span>
        <span class="tabular-nums">{{ formatCurrency(vatTotal, currency) }}</span>
      </div>
      <div class="flex justify-between font-semibold">
        <span>{{ t('total') }}</span>
        <span class="tabular-nums">{{ formatCurrency(total, currency) }}</span>
      </div>
    </div>
  </div>
</template>
