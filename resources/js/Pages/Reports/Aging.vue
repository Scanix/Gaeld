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
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { formatCurrency } from '@/lib/utils'

const props = defineProps({
  report: Object,
  type: { type: String, default: 'receivables' },
})
const { t } = useTranslations()

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
    <div class="mb-6 flex items-center justify-between gap-4">
      <div class="flex items-center gap-1 rounded-lg border border-[hsl(var(--border))] p-1">
        <button
          :class="[
            'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
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
            'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
            selectedType === 'payables'
              ? 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]'
              : 'text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]',
          ]"
          @click="switchType('payables')"
        >
          {{ t('payables') }}
        </button>
      </div>
      <ExportDropdown base-url="/reports/aging/export" :params="exportParams" />
    </div>

    <Card>
      <CardContent class="overflow-x-auto p-0">
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
      </CardContent>
    </Card>
  </AppLayout>
</template>
