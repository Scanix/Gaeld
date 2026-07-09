<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormTextarea from '@/Components/UI/FormTextarea.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { Plus, Trash2, HelpCircle, ArrowUp, ArrowDown, Copy } from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: Array, required: true },
  vatRates: { type: Array, default: () => [] },
  catalogItems: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  currency: { type: String, default: 'CHF' },
  defaultVatRateId: { type: [String, Number], default: null },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

// The parent always passes a reactive array (e.g. Inertia's useForm data), so
// we mutate it in place rather than emitting update:modelValue for every change.
const lines = computed(() => props.modelValue)

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
  ...props.vatRates.map(v => ({ value: v.id, label: `${v.name} (${v.rate}%)` })),
])

const lineTypeOptions = computed(() => [
  { value: 'item', label: t('line_type_item') },
  { value: 'discount', label: t('line_type_discount') },
  { value: 'text', label: t('line_type_text') },
])

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

defineExpose({ subtotal, vatTotal, total })
</script>

<template>
  <div>
    <h3 class="mb-3 text-sm font-medium">{{ t('line_items') }}</h3>
    <div class="space-y-3">
      <div
        v-for="(line, i) in lines"
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
        <div :class="line.type === 'text' ? 'sm:col-span-7' : 'sm:col-span-3'">
          <FormTextarea
            :id="`line-desc-${i}`"
            v-model="line.description"
            :label="t('description')"
            :error="errors[`lines.${i}.description`]"
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
                :error="errors[`lines.${i}.quantity`]"
                required
              />
            </div>
            <div class="sm:col-span-2">
              <FormInput
                :id="`line-price-${i}`"
                v-model="line.unit_price"
                type="number"
                :label="line.type === 'discount' ? (line.discount_type === 'percentage' ? t('discount_percentage') : t('line_type_discount')) : t('unit_price')"
                :error="errors[`lines.${i}.unit_price`]"
                :hint="line.type === 'item' ? t('negative_price_hint') : undefined"
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
        <div class="sm:col-span-2 flex items-end justify-end gap-1 pb-2">
          <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="i === 0"
            :title="t('move_line_up')"
            @click="moveLine(i, -1)"
          >
            <ArrowUp class="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="i === lines.length - 1"
            :title="t('move_line_down')"
            @click="moveLine(i, 1)"
          >
            <ArrowDown class="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            :title="t('duplicate_line')"
            @click="duplicateLine(i)"
          >
            <Copy class="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="lines.length <= 1"
            :title="t('delete')"
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
      <SearchableSelect
        v-if="catalogOptions.length > 0"
        id="add-from-catalog"
        v-model="selectedCatalogItemId"
        :options="catalogOptions"
        :placeholder="t('add_from_catalog')"
        force-searchable
        class="w-56"
      />
    </div>

    <!-- Running totals -->
    <div class="mt-4 space-y-1 border-t pt-3 text-sm">
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
