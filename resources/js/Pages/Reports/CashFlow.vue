<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import ExportDropdown from '@/Components/UI/ExportDropdown.vue'
import SharePrintButton from '@/Components/UI/SharePrintButton.vue'
import HelpText from '@/Components/HelpText.vue'
import { useTranslations } from '@/lib/useTranslations'
import { ChevronDown, ChevronRight, Banknote } from 'lucide-vue-next'
import EmptyState from '@/Components/UI/EmptyState.vue'
import { useFormatters } from '@/lib/useFormatters'

const props = defineProps({ report: Object })
const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const from = ref(props.report?.period?.from ?? '')
const to = ref(props.report?.period?.to ?? '')

const exportParams = computed(() => ({ from: from.value, to: to.value }))

function applyFilter() {
  router.get('/reports/cash-flow', exportParams.value, { preserveState: true })
}

// Collapsible section state
const openSections = ref({ operating: true, investing: true, financing: true })

function toggle(section) {
  openSections.value[section] = !openSections.value[section]
}

function sectionTotal(rows) {
  if (!rows?.length) return 0
  return rows.reduce((s, r) => s + (r.amount ?? 0), 0)
}
</script>

<template>
  <AppLayout :title="t('cash_flow')" help-page="cash-flow">
    <HelpText :title="t('help_cash_flow_title')" class="mb-6">
      <p>{{ t('help_cash_flow_text') }}</p>
    </HelpText>

    <!-- Period selector -->
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div class="flex flex-wrap items-end gap-4">
        <FormInput id="cf-from" v-model="from" type="date" :label="t('from')" />
        <FormInput id="cf-to" v-model="to" type="date" :label="t('to')" />
        <Button @click="applyFilter">{{ t('apply') }}</Button>
      </div>
      <div class="flex items-center gap-2">
        <SharePrintButton :title="t('cash_flow')" />
        <ExportDropdown base-url="/reports/cash-flow/export" :params="exportParams" />
      </div>
    </div>

    <template v-if="report">
      <div class="space-y-4">
        <!-- Operating Activities -->
        <Card>
          <CardHeader>
            <button
              class="flex w-full items-center justify-between text-left"
              @click="toggle('operating')"
            >
              <CardTitle>{{ t('cf_operating') }}</CardTitle>
              <component :is="openSections.operating ? ChevronDown : ChevronRight" class="h-4 w-4 text-[hsl(var(--muted-foreground))]" />
            </button>
          </CardHeader>
          <CardContent v-if="openSections.operating">
            <table class="w-full text-sm">
              <tbody>
                <tr
                  v-for="row in report.operating"
                  :key="row.label"
                  class="border-b last:border-0"
                >
                  <td class="py-2 pr-4 text-[hsl(var(--muted-foreground))]">{{ row.label }}</td>
                  <td
                    class="py-2 text-right tabular-nums"
                    :class="(row.amount ?? 0) >= 0 ? 'text-[hsl(var(--foreground))]' : 'text-red-600'"
                  >
                    {{ formatCurrency(row.amount) }}
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="mt-3 flex justify-between border-t pt-3 text-sm font-semibold">
              <span>{{ t('cf_subtotal_operating') }}</span>
              <span :class="sectionTotal(report.operating) >= 0 ? 'text-green-700' : 'text-red-600'">
                {{ formatCurrency(sectionTotal(report.operating)) }}
              </span>
            </div>
          </CardContent>
        </Card>

        <!-- Investing Activities -->
        <Card>
          <CardHeader>
            <button
              class="flex w-full items-center justify-between text-left"
              @click="toggle('investing')"
            >
              <CardTitle>{{ t('cf_investing') }}</CardTitle>
              <component :is="openSections.investing ? ChevronDown : ChevronRight" class="h-4 w-4 text-[hsl(var(--muted-foreground))]" />
            </button>
          </CardHeader>
          <CardContent v-if="openSections.investing">
            <table class="w-full text-sm">
              <tbody>
                <tr
                  v-for="row in report.investing"
                  :key="row.label"
                  class="border-b last:border-0"
                >
                  <td class="py-2 pr-4 text-[hsl(var(--muted-foreground))]">{{ row.label }}</td>
                  <td
                    class="py-2 text-right tabular-nums"
                    :class="(row.amount ?? 0) >= 0 ? 'text-[hsl(var(--foreground))]' : 'text-red-600'"
                  >
                    {{ formatCurrency(row.amount) }}
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="mt-3 flex justify-between border-t pt-3 text-sm font-semibold">
              <span>{{ t('cf_subtotal_investing') }}</span>
              <span :class="sectionTotal(report.investing) >= 0 ? 'text-green-700' : 'text-red-600'">
                {{ formatCurrency(sectionTotal(report.investing)) }}
              </span>
            </div>
          </CardContent>
        </Card>

        <!-- Financing Activities -->
        <Card>
          <CardHeader>
            <button
              class="flex w-full items-center justify-between text-left"
              @click="toggle('financing')"
            >
              <CardTitle>{{ t('cf_financing') }}</CardTitle>
              <component :is="openSections.financing ? ChevronDown : ChevronRight" class="h-4 w-4 text-[hsl(var(--muted-foreground))]" />
            </button>
          </CardHeader>
          <CardContent v-if="openSections.financing">
            <table class="w-full text-sm">
              <tbody>
                <tr
                  v-for="row in report.financing"
                  :key="row.label"
                  class="border-b last:border-0"
                >
                  <td class="py-2 pr-4 text-[hsl(var(--muted-foreground))]">{{ row.label }}</td>
                  <td
                    class="py-2 text-right tabular-nums"
                    :class="(row.amount ?? 0) >= 0 ? 'text-[hsl(var(--foreground))]' : 'text-red-600'"
                  >
                    {{ formatCurrency(row.amount) }}
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="mt-3 flex justify-between border-t pt-3 text-sm font-semibold">
              <span>{{ t('cf_subtotal_financing') }}</span>
              <span :class="sectionTotal(report.financing) >= 0 ? 'text-green-700' : 'text-red-600'">
                {{ formatCurrency(sectionTotal(report.financing)) }}
              </span>
            </div>
          </CardContent>
        </Card>

        <!-- Summary -->
        <Card>
          <CardContent class="pt-6">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-[hsl(var(--muted-foreground))]">{{ t('cf_beginning_balance') }}</span>
                <span class="tabular-nums font-medium">{{ formatCurrency(report.beginning_balance) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-[hsl(var(--muted-foreground))]">{{ t('cf_net_change') }}</span>
                <span
                  class="tabular-nums font-medium"
                  :class="(report.net_change ?? 0) >= 0 ? 'text-green-700' : 'text-red-600'"
                >
                  {{ formatCurrency(report.net_change) }}
                </span>
              </div>
              <div class="flex justify-between border-t pt-2 text-base font-bold">
                <span>{{ t('cf_ending_balance') }}</span>
                <span class="tabular-nums">{{ formatCurrency(report.ending_balance) }}</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </template>

    <div v-else class="py-8 text-center">
      <EmptyState
        :icon="Banknote"
        :title="t('empty_cash_flow_title')"
        :description="t('empty_cash_flow_desc')"
      />
    </div>
  </AppLayout>
</template>
