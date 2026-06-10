<script setup>
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import CardDescription from '@/Components/UI/CardDescription.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import HelpText from '@/Components/HelpText.vue'
import { useFormatters } from '@/lib/useFormatters'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed, watch } from 'vue'
import { AlertTriangle, Check, CheckCircle2 } from 'lucide-vue-next'

const props = defineProps({
  year:         { type: Number, required: true },
  fromDate:     { type: String, required: true },
  toDate:       { type: String, required: true },
  income:       { type: Array, default: () => [] },
  expenses:     { type: Array, default: () => [] },
  netResult:    { type: String, default: '0' },
  closedYears:  { type: Array, default: () => [] },
  canReopenYear: { type: Boolean, default: false },
  availableYears: { type: Array, default: () => [] },
  unsettledVatPeriods: { type: Array, default: () => [] },
  outstandingInvoices: { type: Array, default: () => [] },
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const selectedYear = ref(props.year)
const showConfirm  = ref(false)
const showReopenConfirm = ref(false)
const processing   = ref(false)

const form = useForm({
  year:                props.year,
  closing_date:        props.toDate,
  reference:           `BOUCL-${props.year}`,
  result_account_code: '9000',
})

const reopenForm = useForm({
  year: props.year,
})

const hasAccounts = computed(() => props.income.length > 0 || props.expenses.length > 0)
const isYearClosed = computed(() => props.closedYears.includes(props.year))
const hasUnsettledVat = computed(() => props.unsettledVatPeriods.length > 0)

const netResultNum = computed(() => parseFloat(props.netResult ?? 0))
const isProfit     = computed(() => netResultNum.value >= 0)

const accountColumns = computed(() => [
  { key: 'code',    label: t('code') },
  { key: 'name',    label: t('account') },
  { key: 'balance', label: t('balance'), class: 'text-right', format: v => formatCurrency(v) },
])

// ── Wizard state ──────────────────────────────────────────────
const currentStep = ref(0)
const steps = computed(() => [
  { key: 'review', label: t('year_end_wizard_step_review') },
  { key: 'outstanding', label: t('year_end_wizard_step_outstanding') },
  { key: 'vat', label: t('year_end_wizard_step_vat') },
  { key: 'confirm', label: t('year_end_wizard_step_confirm') },
])

// Step 3 (VAT) cannot advance while there are unsettled periods.
const canAdvance = computed(() => {
  if (currentStep.value === 2 && hasUnsettledVat.value) return false
  return currentStep.value < steps.value.length - 1
})

function next() {
  if (canAdvance.value) currentStep.value++
}
function back() {
  if (currentStep.value > 0) currentStep.value--
}

watch(selectedYear, (val) => {
  router.get('/accounting/year-end-closing', { year: Number(val) }, { preserveState: true })
})

function runClosing() {
  processing.value = true
  form.post('/accounting/year-end-closing', {
    onFinish: () => {
      processing.value = false
      showConfirm.value = false
    },
  })
}

function runReopen() {
  reopenForm.year = props.year
  reopenForm.post('/accounting/year-end-closing/reopen', {
    onFinish: () => {
      showReopenConfirm.value = false
    },
  })
}

function daysOverdueLabel(n) {
  if (!n || n <= 0) return ''
  return `${n} ${t('days')}`
}
</script>

<template>
  <AppLayout :title="t('year_end_closing')" help-page="accounting-basics">
    <HelpText :title="t('help_year_end_closing_title')" class="mb-6">
      <p>{{ t('help_year_end_closing_text') }}</p>
    </HelpText>

    <!-- Year selector -->
    <div class="mb-6 flex items-center gap-4">
      <FormSelect
        id="year"
        v-model="selectedYear"
        :label="t('fiscal_year')"
        :options="availableYears.map(y => ({ value: y, label: String(y) }))"
      />
      <span
        v-if="isYearClosed"
        class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900/30 dark:text-red-300"
      >
        {{ t('year_closed_badge') }}
      </span>
      <Button
        v-if="isYearClosed && canReopenYear"
        variant="outline"
        class="ml-auto"
        @click="showReopenConfirm = true"
      >
        {{ t('reopen_fiscal_year') }}
      </Button>
    </div>

    <!-- Already-closed year: skip wizard entirely. -->
    <div v-if="isYearClosed" class="rounded-lg border p-6 text-center text-[hsl(var(--muted-foreground))]">
      {{ t('year_closed_badge') }}
    </div>

    <div v-else-if="!hasAccounts" class="rounded-lg border p-6 text-center text-[hsl(var(--muted-foreground))]">
      {{ t('no_accounts_to_close') }}
    </div>

    <div v-else class="space-y-6">
      <!-- Stepper indicator -->
      <nav aria-label="Year-end progress">
        <ol class="flex flex-wrap items-center gap-2">
          <li
            v-for="(step, i) in steps"
            :key="step.key"
            class="flex items-center gap-2"
          >
            <button
              type="button"
              class="flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
              :class="[
                i === currentStep
                  ? 'bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))]'
                  : i < currentStep
                    ? 'bg-[hsl(var(--primary)/0.15)] text-[hsl(var(--primary))]'
                    : 'bg-[hsl(var(--muted))] text-[hsl(var(--muted-foreground))]',
              ]"
              :disabled="i > currentStep"
              @click="i <= currentStep ? currentStep = i : undefined"
            >
              <span
                class="flex h-5 w-5 items-center justify-center rounded-full text-xs"
                :class="i < currentStep ? '' : 'border border-current'"
              >
                <Check v-if="i < currentStep" class="h-3 w-3" />
                <span v-else>{{ i + 1 }}</span>
              </span>
              {{ step.label }}
            </button>
            <span v-if="i < steps.length - 1" class="h-px w-6 bg-[hsl(var(--border))]" />
          </li>
        </ol>
      </nav>

      <!-- Step 1: Review balances -->
      <div v-show="currentStep === 0" class="space-y-6">
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
          {{ t('closing_warning') }}
        </div>

        <Card v-if="income.length">
          <CardHeader>
            <CardTitle>{{ t('income_accounts') }}</CardTitle>
            <CardDescription>{{ fromDate }} – {{ toDate }}</CardDescription>
          </CardHeader>
          <CardContent>
            <DataTable :columns="accountColumns" :rows="income" />
          </CardContent>
        </Card>

        <Card v-if="expenses.length">
          <CardHeader>
            <CardTitle>{{ t('expense_accounts') }}</CardTitle>
            <CardDescription>{{ fromDate }} – {{ toDate }}</CardDescription>
          </CardHeader>
          <CardContent>
            <DataTable :columns="accountColumns" :rows="expenses" />
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-[hsl(var(--muted-foreground))]">{{ t('net_result') }}</span>
              <span
                class="text-xl font-bold"
                :class="isProfit ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
              >
                {{ isProfit ? '+' : '' }}{{ formatCurrency(netResultNum) }}
              </span>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Step 2: Outstanding invoices -->
      <div v-show="currentStep === 1" class="space-y-4">
        <div
          v-if="outstandingInvoices.length === 0"
          class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-950/30 dark:text-green-200"
        >
          <CheckCircle2 class="h-4 w-4 shrink-0" />
          <span>{{ t('year_end_wizard_outstanding_empty') }}</span>
        </div>

        <template v-else>
          <div class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            <AlertTriangle class="h-4 w-4 shrink-0" />
            <span>{{ t('year_end_wizard_outstanding_warning') }}</span>
          </div>

          <Card>
            <CardContent class="p-0">
              <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-[hsl(var(--muted-foreground))]">
                  <tr>
                    <th class="px-4 py-2">{{ t('number') }}</th>
                    <th class="px-4 py-2">{{ t('year_end_wizard_customer') }}</th>
                    <th class="px-4 py-2">{{ t('due_date') }}</th>
                    <th class="px-4 py-2 text-right">{{ t('total') }}</th>
                    <th class="px-4 py-2">{{ t('status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="inv in outstandingInvoices"
                    :key="inv.id"
                    class="border-t"
                  >
                    <td class="px-4 py-2">
                      <a :href="`/invoices/${inv.id}`" class="text-[hsl(var(--primary))] underline">{{ inv.number }}</a>
                    </td>
                    <td class="px-4 py-2">{{ inv.customer_name }}</td>
                    <td class="px-4 py-2">
                      {{ inv.due_date }}
                      <span v-if="inv.days_overdue > 0" class="ml-2 text-xs text-red-600 dark:text-red-400">
                        ({{ daysOverdueLabel(inv.days_overdue) }})
                      </span>
                    </td>
                    <td class="px-4 py-2 text-right">{{ formatCurrency(inv.total) }}</td>
                    <td class="px-4 py-2">{{ t(`invoice_status_${inv.status}`) }}</td>
                  </tr>
                </tbody>
              </table>
            </CardContent>
          </Card>
        </template>
      </div>

      <!-- Step 3: Settle VAT -->
      <div v-show="currentStep === 2" class="space-y-4">
        <div
          v-if="!hasUnsettledVat"
          class="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-950/30 dark:text-green-200"
        >
          <CheckCircle2 class="h-4 w-4 shrink-0" />
          <span>{{ t('year_end_wizard_all_vat_settled') }}</span>
        </div>

        <template v-else>
          <div class="flex items-center gap-3 rounded-lg border border-orange-200 bg-orange-50 p-4 text-sm text-orange-800 dark:border-orange-800 dark:bg-orange-950/30 dark:text-orange-200">
            <AlertTriangle class="h-4 w-4 shrink-0" />
            <span>{{ t('year_end_wizard_vat_blocker') }}</span>
          </div>

          <Card>
            <CardContent class="space-y-2 pt-6">
              <div
                v-for="period in unsettledVatPeriods"
                :key="period"
                class="flex items-center justify-between rounded-md border p-3"
              >
                <span class="font-medium">{{ period }} {{ year }}</span>
                <a
                  :href="`/reports/vat?period=${encodeURIComponent(period)}&year=${year}`"
                  class="text-sm text-[hsl(var(--primary))] underline"
                >
                  {{ t('year_end_wizard_settle_now') }}
                </a>
              </div>
            </CardContent>
          </Card>
        </template>
      </div>

      <!-- Step 4: Confirm closing -->
      <div v-show="currentStep === 3" class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>{{ t('run_closing') }}</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormInput
                id="closing_date"
                v-model="form.closing_date"
                type="date"
                :label="t('closing_date')"
                :error="form.errors.closing_date"
                required
              />
              <FormInput
                id="reference"
                v-model="form.reference"
                :label="t('closing_reference')"
                :error="form.errors.reference"
                placeholder="BOUCL-2025"
                required
              />
              <div class="sm:col-span-2">
                <FormInput
                  id="result_account_code"
                  v-model="form.result_account_code"
                  :label="t('result_account_code')"
                  :error="form.errors.result_account_code"
                  placeholder="9000"
                  required
                />
                <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">
                  {{ t('result_account_code_help') }}
                </p>
              </div>
            </div>

            <div class="mt-6 flex justify-end">
              <Button
                variant="destructive"
                :disabled="form.processing"
                @click="showConfirm = true"
              >
                {{ t('run_closing') }}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Wizard navigation -->
      <div class="flex items-center justify-between border-t pt-4">
        <Button
          type="button"
          variant="outline"
          :disabled="currentStep === 0"
          @click="back"
        >
          {{ t('year_end_wizard_back') }}
        </Button>
        <Button
          v-if="currentStep < steps.length - 1"
          type="button"
          :disabled="!canAdvance"
          @click="next"
        >
          {{ t('year_end_wizard_next') }}
        </Button>
      </div>
    </div>

    <!-- Confirmation dialog -->
    <ConfirmDialog
      :open="showConfirm"
      :title="t('run_closing')"
      :message="t('closing_warning')"
      :confirm-label="t('run_closing')"
      :processing="processing"
      @confirm="runClosing"
      @cancel="showConfirm = false"
    />

    <!-- Reopen confirmation dialog -->
    <ConfirmDialog
      :open="showReopenConfirm"
      :title="t('reopen_fiscal_year')"
      :message="t('reopen_fiscal_year_confirm', { year: props.year })"
      :confirm-label="t('reopen_fiscal_year')"
      :processing="reopenForm.processing"
      @confirm="runReopen"
      @cancel="showReopenConfirm = false"
    />
  </AppLayout>
</template>
