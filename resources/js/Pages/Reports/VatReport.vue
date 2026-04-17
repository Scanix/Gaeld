<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import ExportDropdown from '@/Components/UI/ExportDropdown.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import SharePrintButton from '@/Components/UI/SharePrintButton.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { useClosedFiscalYear } from '@/lib/useClosedFiscalYear'
import ClosedYearBanner from '@/Components/UI/ClosedYearBanner.vue'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { FileSpreadsheet, Receipt } from 'lucide-vue-next'

const props = defineProps({ report: Object })
const { t } = useTranslations()
const { formatCurrency } = useFormatters()

// Period selector: quarter or custom
const mode = ref('quarter')
const year = ref(new Date().getFullYear())
const quarter = ref(Math.ceil((new Date().getMonth() + 1) / 3))
const customFrom = ref('')
const customTo = ref('')

const quarterOptions = [
  { value: 1, label: t('quarter_q1') },
  { value: 2, label: t('quarter_q2') },
  { value: 3, label: t('quarter_q3') },
  { value: 4, label: t('quarter_q4') },
]

const modeOptions = computed(() => [
  { value: 'quarter', label: t('by_quarter') },
  { value: 'custom', label: t('custom_period') },
])

function quarterDates(y, q) {
  const starts = ['01-01', '04-01', '07-01', '10-01']
  const ends   = ['03-31', '06-30', '09-30', '12-31']
  return { from: `${y}-${starts[q - 1]}`, to: `${y}-${ends[q - 1]}` }
}

const exportParams = computed(() => {
  if (mode.value === 'quarter') {
    const { from, to } = quarterDates(year.value, quarter.value)
    return { from_date: from, to_date: to }
  }
  return { from_date: customFrom.value, to_date: customTo.value }
})

function applyFilter() {
  router.get('/reports/vat', exportParams.value, { preserveState: true })
}

// Auto-reload when quarter/year/mode changes
watch([mode, year, quarter], () => {
  if (mode.value === 'quarter') {
    applyFilter()
  }
})

// Settlement confirm dialog
const showSettle = ref(false)
const settleForm = useForm({
  from_date: '',
  to_date: '',
})

function postSettlement() {
  const params = exportParams.value
  settleForm.from_date = params.from_date
  settleForm.to_date = params.to_date
  settleForm.post('/reports/vat/settlement', {
    preserveScroll: true,
    onSuccess: () => { showSettle.value = false },
  })
}

// Closed fiscal year detection based on selected period
const periodYear = computed(() => {
  if (mode.value === 'quarter') return year.value
  // For custom range, use the 'from' date year
  return customFrom.value ? parseInt(customFrom.value.slice(0, 4), 10) : null
})
const { isClosed: isPeriodClosed, closedYear } = useClosedFiscalYear(periodYear)
</script>

<template>
  <AppLayout :title="t('vat_report')" help-page="vat-report">
    <HelpText :title="t('help_vat_title')" class="mb-6">
      <p>{{ t('help_vat_text') }}</p>
    </HelpText>

    <!-- Period selector -->
    <div class="mb-6 flex flex-wrap items-end gap-4">
      <FormSelect
        id="vat-mode"
        v-model="mode"
        :label="t('period_type')"
        :options="modeOptions"
        option-value="value"
        option-label="label"
      />

      <template v-if="mode === 'quarter'">
        <FormInput id="vat-year" v-model.number="year" type="number" :label="t('year')" class="w-28" />
        <FormSelect
          id="vat-quarter"
          v-model.number="quarter"
          :label="t('quarter')"
          :options="quarterOptions"
          option-value="value"
          option-label="label"
        />
      </template>
      <template v-else>
        <FormInput id="vat-from" v-model="customFrom" type="date" :label="t('from')" />
        <FormInput id="vat-to" v-model="customTo" type="date" :label="t('to')" />
      </template>

      <Button v-if="mode === 'custom'" @click="applyFilter">{{ t('apply') }}</Button>

      <div class="ml-auto flex gap-2">
        <SharePrintButton :title="t('vat_report')" />
        <ExportDropdown base-url="/reports/vat/export" :params="exportParams" />
        <Button
          v-if="report"
          variant="outline"
          :disabled="isPeriodClosed"
          :title="isPeriodClosed ? t('fiscal_year_closed_action_disabled') : undefined"
          @click="showSettle = true"
        >
          <FileSpreadsheet class="mr-1.5 h-4 w-4" />
          {{ t('post_settlement_entry') }}
        </Button>
      </div>
    </div>

    <ClosedYearBanner v-if="isPeriodClosed" :year="closedYear" />

    <template v-if="report">
      <!-- Section 200: Umsatz (Revenue) -->
      <Card class="mb-4">
        <CardHeader>
          <CardTitle>{{ t('vat_section_200') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-xs font-medium text-[hsl(var(--muted-foreground))]">
                <th class="pb-2 pr-4">{{ t('vat_line') }}</th>
                <th class="pb-2 pr-4">{{ t('description') }}</th>
                <th class="pb-2 text-right">{{ t('amount') }} (CHF)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in report.revenue_rows" :key="row.line" class="border-b last:border-0">
                <td class="py-2 pr-4 font-mono text-xs text-[hsl(var(--muted-foreground))]">{{ row.line }}</td>
                <td class="py-2 pr-4">{{ row.label }}</td>
                <td class="py-2 text-right tabular-nums">{{ formatCurrency(row.amount) }}</td>
              </tr>
              <tr class="border-t font-semibold">
                <td class="py-2 pr-4 font-mono text-xs">299</td>
                <td class="py-2 pr-4">{{ t('vat_line_299') }}</td>
                <td class="py-2 text-right tabular-nums">{{ formatCurrency(report.total_revenue) }}</td>
              </tr>
            </tbody>
          </table>
          </div>
        </CardContent>
      </Card>

      <!-- Section 300: Steuerberechnung (Output VAT) -->
      <Card class="mb-4">
        <CardHeader>
          <CardTitle>{{ t('vat_section_300') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-xs font-medium text-[hsl(var(--muted-foreground))]">
                <th class="pb-2 pr-4">{{ t('vat_line') }}</th>
                <th class="pb-2 pr-4">{{ t('description') }}</th>
                <th class="pb-2 pr-4 text-right">{{ t('taxable_amount') }}</th>
                <th class="pb-2 pr-4 text-right">{{ t('vat_rate') }}</th>
                <th class="pb-2 text-right">{{ t('vat_amount') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in report.output_vat_rows" :key="row.line" class="border-b last:border-0">
                <td class="py-2 pr-4 font-mono text-xs text-[hsl(var(--muted-foreground))]">{{ row.line }}</td>
                <td class="py-2 pr-4">{{ row.label }}</td>
                <td class="py-2 pr-4 text-right tabular-nums">{{ formatCurrency(row.taxable) }}</td>
                <td class="py-2 pr-4 text-right">{{ row.rate }}%</td>
                <td class="py-2 text-right tabular-nums">{{ formatCurrency(row.vat) }}</td>
              </tr>
              <tr class="border-t font-semibold">
                <td class="py-2 pr-4 font-mono text-xs">399</td>
                <td class="py-2 pr-4">{{ t('vat_line_399') }}</td>
                <td class="py-2 pr-4 text-right tabular-nums">{{ formatCurrency(report.total_taxable) }}</td>
                <td class="py-2 pr-4" />
                <td class="py-2 text-right tabular-nums">{{ formatCurrency(report.total_output_vat) }}</td>
              </tr>
            </tbody>
          </table>
          </div>
        </CardContent>
      </Card>

      <!-- Section 400: Vorsteuer (Input VAT) -->
      <Card class="mb-4">
        <CardHeader>
          <CardTitle>{{ t('vat_section_400') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="flex items-center justify-between py-2 text-sm">
            <div class="flex gap-4">
              <span class="font-mono text-xs text-[hsl(var(--muted-foreground))]">400</span>
              <span>{{ t('vat_line_400') }}</span>
            </div>
            <span class="tabular-nums font-medium">{{ formatCurrency(report.total_input_vat) }}</span>
          </div>
        </CardContent>
      </Card>

      <!-- Section 500: Abrechnung (Settlement) -->
      <Card class="mb-4">
        <CardHeader>
          <CardTitle>{{ t('vat_section_500') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="space-y-2 text-sm">
            <div class="flex items-center justify-between py-1">
              <div class="flex gap-4">
                <span class="font-mono text-xs text-[hsl(var(--muted-foreground))]">500</span>
                <span>{{ t('vat_line_500') }}</span>
              </div>
              <span class="tabular-nums">{{ formatCurrency(report.net_vat) }}</span>
            </div>
            <div class="flex items-center justify-between border-t py-2 font-bold">
              <div class="flex gap-4">
                <span class="font-mono text-xs">510</span>
                <span>{{ t('vat_line_510') }}</span>
              </div>
              <span
                class="tabular-nums text-base"
                :class="(report.vat_payable ?? 0) >= 0 ? 'text-red-600' : 'text-green-600'"
              >
                {{ formatCurrency(report.vat_payable) }}
              </span>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Help context -->
      <div class="mt-4 rounded-lg border border-[hsl(var(--border))] bg-[hsl(var(--muted))]/30 p-4 text-xs text-[hsl(var(--muted-foreground))]">
        <p class="font-medium mb-1">{{ t('vat_form_help_title') }}</p>
        <ul class="list-disc ml-4 space-y-0.5">
          <li>{{ t('vat_form_help_200') }}</li>
          <li>{{ t('vat_form_help_300') }}</li>
          <li>{{ t('vat_form_help_400') }}</li>
          <li>{{ t('vat_form_help_500') }}</li>
        </ul>
      </div>
    </template>

    <div v-else class="py-8 text-center">
      <EmptyState
        :icon="Receipt"
        :title="t('empty_vat_report_title')"
        :description="t('empty_vat_report_desc')"
      />
    </div>

    <!-- Settle confirmation dialog -->
    <ConfirmDialog
      :open="showSettle"
      :title="t('post_settlement_entry')"
      :message="t('post_settlement_confirm')"
      :confirm-label="t('post')"
      confirm-variant="default"
      :processing="settleForm.processing"
      :errors="settleForm.errors"
      @confirm="postSettlement"
      @cancel="showSettle = false"
    />
  </AppLayout>
</template>
