<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Badge from '@/Components/UI/Badge.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import Button from '@/Components/UI/Button.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { computed, ref } from 'vue'
import { Eye, FileText } from 'lucide-vue-next'
import EmptyState from '@/Components/UI/EmptyState.vue'

const { t } = useTranslations()
const { intlMonthName } = useFormatters()

const props = defineProps({
  slips: Object,
  query: {
    type: Object,
    default: () => ({ month: '', year: '' }),
  },
})

const year = ref(props.query.year || new Date().getFullYear().toString())
const month = ref(props.query.month || '')

const yearOptions = computed(() => {
  const current = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({ value: String(current - i), label: String(current - i) }))
})

const monthOptions = computed(() => [
  { value: '', label: t('all_months') },
  ...Array.from({ length: 12 }, (_, i) => ({
    value: String(i + 1).padStart(2, '0'),
    label: intlMonthName(i),
  })),
])

function applyFilter() {
  router.get('/payroll/salary-slips', { year: year.value, month: month.value, page: 1 }, { preserveState: true, replace: true })
}

const { locale: appLocale } = useFormatters()
function formatSwiss(v) {
  if (v == null) return '—'
  const intlMap = { en: 'en-CH', fr: 'fr-CH', de: 'de-CH', it: 'it-CH' }
  return Number(v).toLocaleString(intlMap[appLocale.value] || 'de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const columns = computed(() => [
  { key: 'period', label: t('period') },
  { key: 'employee', label: t('employee') },
  { key: 'gross_salary', label: t('gross_salary'), class: 'text-right' },
  { key: 'net_salary', label: t('net_salary'), class: 'text-right' },
  { key: 'status', label: t('status') },
  { key: 'actions', label: '', class: 'text-right w-20' },
])
</script>

<template>
  <AppLayout :title="t('salary_slips')" help-page="payroll">
    <!-- Filters -->
    <div class="mb-6 flex items-end gap-4">
      <FormSelect
        id="year"
        v-model="year"
        :label="t('year')"
        :options="yearOptions"
        class="w-32"
      />
      <FormSelect
        id="month"
        v-model="month"
        :label="t('month')"
        :options="monthOptions"
        class="w-36"
      />
      <Button class="mb-0.5" @click="applyFilter">{{ t('apply') }}</Button>
    </div>

    <DataTable
      :columns="columns"
      :rows="slips?.data ?? []"
      :pagination="slips"
    >
      <template #cell-period="{ row }">{{ row.month_label }}</template>
      <template #cell-employee="{ row }">
        <Link :href="`/payroll/employees/${row.employee_id}`" class="hover:underline">
          {{ row.employee_name }}
        </Link>
      </template>
      <template #cell-gross_salary="{ row }">
        <span class="font-mono">CHF {{ formatSwiss(row.gross_salary) }}</span>
      </template>
      <template #cell-net_salary="{ row }">
        <span class="font-mono">CHF {{ formatSwiss(row.net_salary) }}</span>
      </template>
      <template #cell-status="{ row }">
        <Badge :variant="row.status === 'posted' ? 'default' : 'secondary'">
          {{ t('slip_status_' + row.status) }}
        </Badge>
      </template>
      <template #cell-actions="{ row }">
        <Link :href="`/payroll/salary-slips/${row.id}`" class="text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
          <Eye class="h-4 w-4" />
        </Link>
      </template>
      <template #empty>
        <EmptyState :icon="FileText" :title="t('no_salary_slips_yet')" :description="t('no_salary_slips_yet_desc')" />
      </template>
    </DataTable>
  </AppLayout>
</template>
