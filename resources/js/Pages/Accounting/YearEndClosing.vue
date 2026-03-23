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
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { formatCurrency } from '@/lib/utils'
import { useTranslations } from '@/lib/useTranslations'
import { ref, computed } from 'vue'

const props = defineProps({
  year:      { type: Number, required: true },
  fromDate:  { type: String, required: true },
  toDate:    { type: String, required: true },
  income:    { type: Array, default: () => [] },
  expenses:  { type: Array, default: () => [] },
  netResult: { type: String, default: '0' },
})

const { t } = useTranslations()

const selectedYear = ref(props.year)
const showConfirm  = ref(false)
const processing   = ref(false)

const form = useForm({
  year:                props.year,
  closing_date:        props.toDate,
  reference:           `BOUCL-${props.year}`,
  result_account_code: '9000',
})

const hasAccounts = computed(() => props.income.length > 0 || props.expenses.length > 0)

const netResultNum = computed(() => parseFloat(props.netResult ?? 0))
const isProfit     = computed(() => netResultNum.value >= 0)

const accountColumns = computed(() => [
  { key: 'code',    label: t('code') },
  { key: 'name',    label: t('account') },
  { key: 'balance', label: t('balance'), class: 'text-right', format: v => formatCurrency(v) },
])

function applyYear() {
  router.get('/accounting/year-end-closing', { year: selectedYear.value }, { preserveState: true })
}

function runClosing() {
  processing.value = true
  form.post('/accounting/year-end-closing', {
    onFinish: () => {
      processing.value = false
      showConfirm.value = false
    },
  })
}
</script>

<template>
  <AppLayout :title="t('year_end_closing')">
    <!-- Year selector -->
    <div class="mb-6 flex items-end gap-4">
      <FormInput
        id="year"
        v-model="selectedYear"
        type="number"
        :label="t('fiscal_year')"
      />
      <Button variant="outline" @click="applyYear">{{ t('apply') }}</Button>
    </div>

    <!-- Info banner -->
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
      {{ t('closing_warning') }}
    </div>

    <div v-if="!hasAccounts" class="rounded-lg border p-6 text-center text-[hsl(var(--muted-foreground))]">
      {{ t('no_accounts_to_close') }}
    </div>

    <div v-else class="space-y-6">
      <!-- Income accounts -->
      <Card v-if="income.length">
        <CardHeader>
          <CardTitle>{{ t('income_accounts') }}</CardTitle>
          <CardDescription>{{ fromDate }} – {{ toDate }}</CardDescription>
        </CardHeader>
        <CardContent>
          <DataTable :columns="accountColumns" :rows="income" />
        </CardContent>
      </Card>

      <!-- Expense accounts -->
      <Card v-if="expenses.length">
        <CardHeader>
          <CardTitle>{{ t('expense_accounts') }}</CardTitle>
          <CardDescription>{{ fromDate }} – {{ toDate }}</CardDescription>
        </CardHeader>
        <CardContent>
          <DataTable :columns="accountColumns" :rows="expenses" />
        </CardContent>
      </Card>

      <!-- Net result -->
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

      <!-- Closing form -->
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
  </AppLayout>
</template>
