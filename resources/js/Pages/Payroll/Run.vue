<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { useClosedFiscalYear } from '@/lib/useClosedFiscalYear'
import ClosedYearBanner from '@/Components/UI/ClosedYearBanner.vue'
import { Check, ChevronRight } from 'lucide-vue-next'

const { t } = useTranslations()
const { intlMonthName, formatCurrency } = useFormatters()

const props = defineProps({
  employees: { type: Array, default: () => [] },
  fiscalYears: { type: Array, default: () => [] },
  withholdingTaxEnabled: { type: Boolean, default: false },
})

// Step state: 1=Select, 2=Preview, 3=Generate, 4=Post
const step = ref(1)
const selectedEmployeeIds = ref([])
const adjustments = ref(Object.fromEntries(
  props.employees.map(employee => [employee.id, {
    unpaid_leave_days: 0,
    reimbursement_amount: '0.00',
  }])
))
const month = ref(String(((new Date().getMonth() + 11) % 12) + 1))
const year = ref(
  // Default to last month's year (handles January → previous year).
  (() => {
    const now = new Date()
    const lastMonthYear = now.getMonth() === 0 ? now.getFullYear() - 1 : now.getFullYear()
    return props.fiscalYears.length
      ? String(props.fiscalYears.includes(lastMonthYear) ? lastMonthYear : props.fiscalYears[0])
      : String(lastMonthYear)
  })()
)
const preview = ref([])
const generatedSlipIds = ref([])
const generating = ref(false)
const previewing = ref(false)
const posting = ref(false)

const payrollPeriodEnd = computed(() => {
  const periodYear = Number.parseInt(year.value, 10)
  const periodMonth = Number.parseInt(month.value, 10)

  if (!Number.isFinite(periodYear) || !Number.isFinite(periodMonth)) return null

  return new Date(Date.UTC(periodYear, periodMonth, 0)).toISOString().slice(0, 10)
})

const { isClosed: isYearClosed, closedYear } = useClosedFiscalYear(payrollPeriodEnd)
const errorMessage = ref('')

const monthOptions = computed(() =>
  Array.from({ length: 12 }, (_, i) => ({
    value: String(i + 1),
    label: intlMonthName(i),
  }))
)

const yearOptions = computed(() =>
  props.fiscalYears.length
    ? props.fiscalYears.map(y => ({ value: String(y), label: String(y) }))
    : Array.from({ length: 3 }, (_, i) => {
        const v = new Date().getFullYear() - i
        return { value: String(v), label: String(v) }
      })
)

const steps = computed(() => [
  { n: 1, label: t('payroll_step_select') },
  { n: 2, label: t('payroll_step_preview') },
  { n: 3, label: t('payroll_step_generate') },
  { n: 4, label: t('payroll_step_post') },
])

function toggleEmployee(id) {
  if (selectedEmployeeIds.value.includes(id)) {
    selectedEmployeeIds.value = selectedEmployeeIds.value.filter(e => e !== id)
  } else {
    selectedEmployeeIds.value.push(id)
  }
}

function toggleAll() {
  if (selectedEmployeeIds.value.length === props.employees.length) {
    selectedEmployeeIds.value = []
  } else {
    selectedEmployeeIds.value = props.employees.map(e => e.id)
  }
}

function adjustmentFor(employeeId) {
  return adjustments.value[employeeId]
}

function adjustmentPayload() {
  return selectedEmployeeIds.value.map(employeeId => {
    const adjustment = adjustmentFor(employeeId)

    return {
      employee_id: employeeId,
      unpaid_leave_days: Number(adjustment?.unpaid_leave_days) || 0,
      reimbursement_amount: adjustment?.reimbursement_amount || '0.00',
    }
  })
}

async function goToPreview() {
  previewing.value = true
  errorMessage.value = ''

  try {
    const response = await fetch('/payroll/run/preview', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
      },
      body: JSON.stringify({
        employee_ids: selectedEmployeeIds.value,
        month: month.value,
        year: year.value,
        adjustments: adjustmentPayload(),
      }),
    })

    if (!response.ok) {
      const err = await response.json().catch(() => ({}))
      errorMessage.value = err.message || t('payroll_generate_error')
      return
    }

    const data = await response.json()
    const employeesById = new Map(props.employees.map(employee => [employee.id, employee]))

    preview.value = (data.data ?? []).map(slip => {
      const employee = employeesById.get(slip.employee_id) ?? {}
      const deductions = slip.deductions ?? {}

      return {
        ...employee,
        id: slip.employee_id,
        gross_salary: slip.gross_salary,
        avs: deductions.avs_employee ?? '0.00',
        ac: deductions.ac_employee ?? '0.00',
        aanp: deductions.aanp_employee ?? '0.00',
        lpp: deductions.lpp_employee ?? '0.00',
        base_salary: deductions.base_salary ?? slip.gross_salary,
        thirteenth_salary: deductions.thirteenth_salary ?? '0.00',
        unpaid_leave_amount: deductions.unpaid_leave_amount ?? '0.00',
        reimbursement_amount: deductions.reimbursement_amount ?? '0.00',
        source_tax: deductions.source_tax ?? '0.00',
        net: slip.net_salary,
      }
    })
    step.value = 2
  } catch {
    errorMessage.value = t('payroll_generate_error')
  } finally {
    previewing.value = false
  }
}

async function generateSlips() {
  generating.value = true
  errorMessage.value = ''
  try {
    const response = await fetch('/payroll/run', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
      },
      body: JSON.stringify({
        employee_ids: selectedEmployeeIds.value,
        month: month.value,
        year: year.value,
        adjustments: adjustmentPayload(),
      }),
    })
    if (!response.ok) {
      const err = await response.json().catch(() => ({}))
      errorMessage.value = err.message || t('payroll_generate_error')
      return
    }
    const data = await response.json()
    generatedSlipIds.value = data.slip_ids ?? []
    step.value = 3
  } catch {
    errorMessage.value = t('payroll_generate_error')
  } finally {
    generating.value = false
  }
}

async function postSlips() {
  posting.value = true
  errorMessage.value = ''
  try {
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content ?? ''
    for (const slipId of generatedSlipIds.value) {
      const response = await fetch(`/payroll/salary-slips/${slipId}/post`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      })
      if (!response.ok) {
        const err = await response.json().catch(() => ({}))
        errorMessage.value = err.message || t('payroll_post_error')
        return
      }
    }
    step.value = 4
  } catch {
    errorMessage.value = t('payroll_post_error')
  } finally {
    posting.value = false
  }
}
</script>

<template>
  <AppLayout :title="t('run_payroll')" help-page="payroll">
    <p v-if="errorMessage" class="mb-4 text-sm text-[hsl(var(--destructive))]">{{ errorMessage }}</p>

    <ClosedYearBanner v-if="isYearClosed" :year="closedYear" />

    <!-- Step indicator -->
    <nav class="mb-8">
      <ol class="flex flex-wrap items-center gap-y-2">
        <li
          v-for="(s, idx) in steps"
          :key="s.n"
          class="flex items-center"
        >
          <div class="flex items-center gap-2">
            <span
              :class="[
                'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                step > s.n ? 'bg-[hsl(var(--primary))] text-white' :
                step === s.n ? 'bg-[hsl(var(--primary))] text-white' :
                'bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]',
              ]"
            >
              <Check v-if="step > s.n" class="h-4 w-4" />
              <span v-else>{{ s.n }}</span>
            </span>
            <span
              :class="[
                'text-sm font-medium hidden sm:inline',
                step === s.n ? 'text-[hsl(var(--foreground))]' : 'text-[hsl(var(--muted-foreground))]',
              ]"
            >{{ s.label }}</span>
          </div>
          <ChevronRight v-if="idx < steps.length - 1" class="mx-2 h-4 w-4 shrink-0 text-[hsl(var(--muted-foreground))] sm:mx-3" />
        </li>
      </ol>
    </nav>

    <!-- Step 1: Select employees + period -->
    <Card v-if="step === 1">
      <CardHeader>
        <CardTitle>{{ t('payroll_step_select') }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-6">
        <div class="flex flex-wrap gap-4">
          <FormSelect id="month" v-model="month" :label="t('month')" :options="monthOptions" class="w-full sm:w-40" />
          <FormSelect id="year" v-model="year" :label="t('year')" :options="yearOptions" class="w-full sm:w-28" />
        </div>

        <div>
          <div class="mb-3 flex items-center justify-between">
            <p class="text-sm font-medium">{{ t('select_employees') }}</p>
            <button class="text-xs text-[hsl(var(--primary))] hover:underline" @click="toggleAll">
              {{ selectedEmployeeIds.length === employees.length ? t('deselect_all') : t('select_all') }}
            </button>
          </div>
          <div class="space-y-2">
            <label
              v-for="emp in employees"
              :key="emp.id"
              class="flex cursor-pointer items-center gap-3 rounded-lg border border-[hsl(var(--border))] p-3 hover:bg-[hsl(var(--accent))]"
              :class="{ 'border-[hsl(var(--primary))] bg-[hsl(var(--accent))]': selectedEmployeeIds.includes(emp.id) }"
            >
              <input
                type="checkbox"
                :checked="selectedEmployeeIds.includes(emp.id)"
                class="h-4 w-4 accent-[hsl(var(--primary))]"
                @change="toggleEmployee(emp.id)"
              />
              <div class="flex-1">
                <p class="font-medium text-sm">{{ emp.first_name }} {{ emp.last_name }}</p>
                <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ emp.position }} — {{ formatCurrency(emp.gross_salary) }}{{ t('per_month') }}</p>
              </div>
            </label>
          </div>
        </div>

        <div v-if="selectedEmployeeIds.length" class="space-y-3 border-t border-[hsl(var(--border))] pt-5">
          <div>
            <p class="text-sm font-medium">{{ t('payroll_adjustments') }}</p>
            <p class="text-xs text-[hsl(var(--muted-foreground))]">{{ t('payroll_adjustments_desc') }}</p>
          </div>
          <div
            v-for="emp in employees.filter(employee => selectedEmployeeIds.includes(employee.id))"
            :key="emp.id"
            class="grid grid-cols-1 gap-3 rounded-lg border border-[hsl(var(--border))] p-3 sm:grid-cols-[1fr_10rem_10rem] sm:items-end"
          >
            <p class="text-sm font-medium">{{ emp.first_name }} {{ emp.last_name }}</p>
            <label class="text-xs text-[hsl(var(--muted-foreground))]">
              {{ t('unpaid_leave_days') }}
              <input
                v-model.number="adjustmentFor(emp.id).unpaid_leave_days"
                type="number"
                min="0"
                max="31"
                class="mt-1 flex h-9 w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-2 text-sm text-[hsl(var(--foreground))]"
              />
            </label>
            <label class="text-xs text-[hsl(var(--muted-foreground))]">
              {{ t('reimbursement_amount') }}
              <input
                v-model="adjustmentFor(emp.id).reimbursement_amount"
                type="number"
                min="0"
                step="0.01"
                class="mt-1 flex h-9 w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-2 text-sm text-[hsl(var(--foreground))]"
              />
            </label>
          </div>
        </div>

        <div class="flex justify-end">
          <Button :disabled="!selectedEmployeeIds.length || previewing" @click="goToPreview">
            {{ t('preview') }}
            <ChevronRight class="ml-2 h-4 w-4" />
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Step 2: Preview -->
    <Card v-else-if="step === 2">
      <CardHeader>
        <CardTitle>{{ t('payroll_step_preview') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[hsl(var(--border))] text-[hsl(var(--muted-foreground))]">
                <th class="pb-2 text-left font-medium">{{ t('employee') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('gross_salary') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('thirteenth_salary') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('unpaid_leave') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('expense_reimbursement') }}</th>
                <th class="pb-2 text-right font-medium">AVS</th>
                <th class="pb-2 text-right font-medium">AC</th>
                <th class="pb-2 text-right font-medium">AANP</th>
                <th class="pb-2 text-right font-medium">LPP</th>
                <th v-if="withholdingTaxEnabled" class="pb-2 text-right font-medium">{{ t('withholding_tax') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('net_salary') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[hsl(var(--border))]">
              <tr v-for="emp in preview" :key="emp.id">
                <td class="py-2.5">{{ emp.first_name }} {{ emp.last_name }}</td>
                <td class="py-2.5 text-right font-mono">{{ formatCurrency(emp.gross_salary) }}</td>
                <td class="py-2.5 text-right font-mono">{{ formatCurrency(emp.thirteenth_salary) }}</td>
                <td class="py-2.5 text-right font-mono text-red-600">{{ formatCurrency(-emp.unpaid_leave_amount) }}</td>
                <td class="py-2.5 text-right font-mono text-green-700 dark:text-green-400">{{ formatCurrency(emp.reimbursement_amount) }}</td>
                <td class="py-2.5 text-right font-mono text-red-600">{{ formatCurrency(-emp.avs) }}</td>
                <td class="py-2.5 text-right font-mono text-red-600">{{ formatCurrency(-emp.ac) }}</td>
                <td class="py-2.5 text-right font-mono text-red-600">{{ formatCurrency(-emp.aanp) }}</td>
                <td class="py-2.5 text-right font-mono text-red-600">{{ formatCurrency(-emp.lpp) }}</td>
                <td v-if="withholdingTaxEnabled" class="py-2.5 text-right font-mono text-red-600">{{ formatCurrency(-emp.source_tax) }}</td>
                <td class="py-2.5 text-right font-mono font-bold text-green-700 dark:text-green-400">{{ formatCurrency(emp.net) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-6 flex justify-between">
          <Button variant="outline" @click="step = 1">{{ t('back') }}</Button>
          <Button :disabled="generating || isYearClosed" :title="isYearClosed ? t('fiscal_year_closed_action_disabled') : undefined" @click="generateSlips">
            {{ generating ? t('generating') + '…' : t('payroll_step_generate') }}
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Step 3: Generated — ready to post -->
    <Card v-else-if="step === 3">
      <CardHeader>
        <CardTitle>{{ t('payroll_slips_generated') }}</CardTitle>
      </CardHeader>
      <CardContent class="space-y-4">
        <p class="text-sm text-[hsl(var(--muted-foreground))]">
          {{ t('payroll_generated_count', { count: generatedSlipIds.length }) }}
        </p>
        <div class="flex gap-3">
          <Button :disabled="posting || isYearClosed" :title="isYearClosed ? t('fiscal_year_closed_action_disabled') : undefined" @click="postSlips">
            {{ posting ? t('posting') + '…' : t('payroll_step_post') }}
          </Button>
          <Button variant="outline" as="a" href="/payroll/salary-slips">
            {{ t('view_salary_slips') }}
          </Button>
        </div>
      </CardContent>
    </Card>

    <!-- Step 4: Done -->
    <Card v-else>
      <CardContent class="py-12 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
          <Check class="h-8 w-8 text-green-600 dark:text-green-400" />
        </div>
        <h2 class="mb-2 text-xl font-bold">{{ t('payroll_done') }}</h2>
        <p class="mb-6 text-sm text-[hsl(var(--muted-foreground))]">{{ t('payroll_done_desc') }}</p>
        <div class="flex justify-center gap-3">
          <Button as="a" href="/payroll/salary-slips">{{ t('view_salary_slips') }}</Button>
          <Button variant="outline" @click="step = 1; selectedEmployeeIds = []; generatedSlipIds = []">
            {{ t('run_another') }}
          </Button>
        </div>
      </CardContent>
    </Card>
  </AppLayout>
</template>
