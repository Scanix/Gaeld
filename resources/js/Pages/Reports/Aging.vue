<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import ExportDropdown from '@/Components/UI/ExportDropdown.vue'
import SharePrintButton from '@/Components/UI/SharePrintButton.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { useMediaQuery } from '@/lib/useMediaQuery'

const props = defineProps({
  report: Object,
  type: { type: String, default: 'receivables' },
})
const { t } = useTranslations()
const { formatCurrency } = useFormatters()
const isMobile = useMediaQuery('(max-width: 639px)')

const selectedType = ref(props.type)

const exportParams = computed(() => ({ type: selectedType.value }))

function switchType(type) {
  selectedType.value = type
  router.get('/reports/aging', { type }, { preserveState: true })
}

// Color classes by aging bracket
const bracketColors = {
  current:  'text-green-700',
  b1_30:    'text-yellow-600',
  b31_60:   'text-orange-600',
  b61_90:   'text-red-600',
  b90plus:  'text-red-900 font-semibold',
}

const brackets = ['current', 'b1_30', 'b31_60', 'b61_90', 'b90plus']

function bracketSum(key) {
  if (!props.report?.rows?.length) return 0
  return props.report.rows.reduce((s, r) => s + (r[key] ?? 0), 0)
}

function rowTotal(row) {
  return brackets.reduce((s, b) => s + (row[b] ?? 0), 0)
}

function grandTotal() {
  if (!props.report?.rows?.length) return 0
  return props.report.rows.reduce((s, r) => s + rowTotal(r), 0)
}
</script>

<template>
  <AppLayout :title="t('aging_report')" help-page="aging">
    <HelpText :title="t('help_aging_title')" class="mb-6">
      <p>{{ t('help_aging_text') }}</p>
    </HelpText>

    <!-- Type toggle + export -->
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-1 rounded-lg border border-[hsl(var(--border))] p-1 self-start">
        <button
          :class="[
            'rounded-md px-3 py-2 text-sm font-medium transition-colors sm:px-4 sm:py-1.5',
            selectedType === 'receivables'
              ? 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]'
              : 'text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]',
          ]"
          @click="switchType('receivables')"
        >
          {{ t('receivables') }}
        </button>
        <button
          :class="[
            'rounded-md px-3 py-2 text-sm font-medium transition-colors sm:px-4 sm:py-1.5',
            selectedType === 'payables'
              ? 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]'
              : 'text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]',
          ]"
          @click="switchType('payables')"
        >
          {{ t('payables') }}
        </button>
      </div>
      <div class="flex items-center gap-2">
        <SharePrintButton :title="t('aging_report')" />
        <ExportDropdown base-url="/reports/aging/export" :params="exportParams" />
      </div>
    </div>

    <Card>
      <CardContent class="p-0">
        <!-- Mobile card view -->
        <div v-if="isMobile" class="divide-y divide-[hsl(var(--border))]">
          <template v-if="report?.rows?.length">
            <div v-for="row in report.rows" :key="row.id" class="px-4 py-3 space-y-2">
              <div class="flex justify-between items-center">
                <span class="font-medium text-sm">{{ row.name }}</span>
                <span class="text-sm font-semibold tabular-nums">{{ formatCurrency(rowTotal(row)) }}</span>
              </div>
              <div class="text-xs text-[hsl(var(--muted-foreground))]">
                {{ row.document_number }} · {{ row.date }} · {{ t('due') }}: {{ row.due_date }}
              </div>
              <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                <span v-if="row.current" :class="bracketColors.current">{{ t('aging_current') }}: {{ formatCurrency(row.current) }}</span>
                <span v-if="row.b1_30" :class="bracketColors.b1_30">1–30: {{ formatCurrency(row.b1_30) }}</span>
                <span v-if="row.b31_60" :class="bracketColors.b31_60">31–60: {{ formatCurrency(row.b31_60) }}</span>
                <span v-if="row.b61_90" :class="bracketColors.b61_90">61–90: {{ formatCurrency(row.b61_90) }}</span>
                <span v-if="row.b90plus" :class="bracketColors.b90plus">90+: {{ formatCurrency(row.b90plus) }}</span>
              </div>
            </div>
          </template>
          <div v-else class="px-4 py-8 text-center text-[hsl(var(--muted-foreground))]">
            {{ t('no_aging_entries') }}
          </div>
          <!-- Mobile totals -->
          <div v-if="report?.rows?.length" class="px-4 py-3 bg-[hsl(var(--muted))]/30 space-y-1">
            <div class="flex justify-between text-xs font-bold">
              <span>{{ t('totals') }}</span>
              <span class="tabular-nums">{{ formatCurrency(grandTotal()) }}</span>
            </div>
            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs font-semibold">
              <span class="text-green-700 tabular-nums">{{ t('aging_current') }}: {{ formatCurrency(bracketSum('current')) }}</span>
              <span class="text-yellow-600 tabular-nums">1–30: {{ formatCurrency(bracketSum('b1_30')) }}</span>
              <span class="text-orange-600 tabular-nums">31–60: {{ formatCurrency(bracketSum('b31_60')) }}</span>
              <span class="text-red-600 tabular-nums">61–90: {{ formatCurrency(bracketSum('b61_90')) }}</span>
              <span class="text-red-900 tabular-nums">90+: {{ formatCurrency(bracketSum('b90plus')) }}</span>
            </div>
          </div>
        </div>

        <!-- Desktop table view -->
        <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-[hsl(var(--muted))]/50 text-xs font-medium text-[hsl(var(--muted-foreground))]">
              <th class="px-4 py-3 text-left">{{ t('name') }}</th>
              <th class="px-4 py-3 text-left">{{ t('document_number') }}</th>
              <th class="px-4 py-3 text-left">{{ t('date') }}</th>
              <th class="px-4 py-3 text-left">{{ t('due_date') }}</th>
              <th class="px-4 py-3 text-right text-green-700">{{ t('aging_current') }}</th>
              <th class="px-4 py-3 text-right text-yellow-600">1–30</th>
              <th class="px-4 py-3 text-right text-orange-600">31–60</th>
              <th class="px-4 py-3 text-right text-red-600">61–90</th>
              <th class="px-4 py-3 text-right text-red-900">90+</th>
              <th class="px-4 py-3 text-right font-semibold text-[hsl(var(--foreground))]">{{ t('total') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="report?.rows?.length">
              <tr
                v-for="row in report.rows"
                :key="row.id"
                class="border-b last:border-0 hover:bg-[hsl(var(--accent))]/40"
              >
                <td class="px-4 py-2.5 font-medium">{{ row.name }}</td>
                <td class="px-4 py-2.5 text-[hsl(var(--muted-foreground))]">{{ row.document_number }}</td>
                <td class="px-4 py-2.5 text-[hsl(var(--muted-foreground))]">{{ row.date }}</td>
                <td class="px-4 py-2.5 text-[hsl(var(--muted-foreground))]">{{ row.due_date }}</td>
                <td class="px-4 py-2.5 text-right tabular-nums" :class="bracketColors.current">
                  {{ row.current ? formatCurrency(row.current) : '—' }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums" :class="bracketColors.b1_30">
                  {{ row.b1_30 ? formatCurrency(row.b1_30) : '—' }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums" :class="bracketColors.b31_60">
                  {{ row.b31_60 ? formatCurrency(row.b31_60) : '—' }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums" :class="bracketColors.b61_90">
                  {{ row.b61_90 ? formatCurrency(row.b61_90) : '—' }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums" :class="bracketColors.b90plus">
                  {{ row.b90plus ? formatCurrency(row.b90plus) : '—' }}
                </td>
                <td class="px-4 py-2.5 text-right tabular-nums font-semibold">
                  {{ formatCurrency(rowTotal(row)) }}
                </td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="10" class="px-4 py-8 text-center text-[hsl(var(--muted-foreground))]">
                {{ t('no_aging_entries') }}
              </td>
            </tr>
          </tbody>
          <!-- Totals footer -->
          <tfoot v-if="report?.rows?.length">
            <tr class="border-t-2 bg-[hsl(var(--muted))]/30 text-xs font-bold">
              <td colspan="4" class="px-4 py-3 text-[hsl(var(--muted-foreground))]">{{ t('totals') }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-green-700">{{ formatCurrency(bracketSum('current')) }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-yellow-600">{{ formatCurrency(bracketSum('b1_30')) }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-orange-600">{{ formatCurrency(bracketSum('b31_60')) }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-red-600">{{ formatCurrency(bracketSum('b61_90')) }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-red-900">{{ formatCurrency(bracketSum('b90plus')) }}</td>
              <td class="px-4 py-3 text-right tabular-nums text-[hsl(var(--foreground))]">{{ formatCurrency(grandTotal()) }}</td>
            </tr>
          </tfoot>
        </table>
        </div>
      </CardContent>
    </Card>
  </AppLayout>
</template>
