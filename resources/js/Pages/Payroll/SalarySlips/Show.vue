<script setup>
import { ref } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const props = defineProps({
  slip: Object,
  canManage: { type: Boolean, default: true },
})

const postForm = useForm({})
const showUnpostDialog = ref(false)
const showDeleteDialog = ref(false)
const unposting = ref(false)
const deleting = ref(false)

function postToLedger() {
  postForm.post(`/payroll/salary-slips/${props.slip.id}/post`)
}

function unpost() {
  unposting.value = true
  router.post(`/payroll/salary-slips/${props.slip.id}/unpost`, {}, {
    onFinish: () => {
      unposting.value = false
      showUnpostDialog.value = false
    },
  })
}

function executeDelete() {
  deleting.value = true
  router.delete(`/payroll/salary-slips/${props.slip.id}`, {
    onFinish: () => { deleting.value = false },
  })
}

function downloadPdf() {
  window.open(`/payroll/salary-slips/${props.slip.id}/pdf`, '_blank')
}

function deductionRow(label, employee, employer) {
  return { label, employee, employer, total: (Number(employee) || 0) + (Number(employer) || 0) }
}
</script>

<template>
  <AppLayout :title="t('salary_slip')" help-page="payroll">
    <Breadcrumb
      :items="[{ label: t('payroll'), href: '/payroll/employees' }, { label: t('salary_slips'), href: '/payroll/salary-slips' }, { label: slip.month_label }]"
      class="mb-4"
    />

    <div class="max-w-2xl space-y-6">
      <!-- Header card -->
      <Card>
        <CardHeader>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <CardTitle>{{ t('salary_slip') }} — {{ slip.month_label }}</CardTitle>
              <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">
                <Link v-if="canManage" :href="`/payroll/employees/${slip.employee_id}`" class="hover:underline">
                  {{ slip.employee_name }}
                </Link>
                <span v-else>{{ slip.employee_name }}</span>
              </p>
            </div>
            <Badge :variant="slip.status === 'posted' ? 'default' : 'secondary'">
              {{ t('slip_status_' + slip.status) }}
            </Badge>
          </div>
        </CardHeader>
      </Card>

      <!-- Salary breakdown -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('salary_breakdown') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[hsl(var(--border))] text-[hsl(var(--muted-foreground))]">
                <th class="pb-2 text-left font-medium">{{ t('item') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('employee_share') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('employer_share') }}</th>
                <th class="pb-2 text-right font-medium">{{ t('total') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[hsl(var(--border))]">
              <tr v-if="slip.adjustments?.base_salary && Number(slip.adjustments.base_salary) !== Number(slip.gross_salary)" class="text-[hsl(var(--muted-foreground))]">
                <td class="py-2">{{ t('base_salary') }}</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(slip.adjustments.base_salary) }}</td>
                <td class="py-2 text-right">—</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(slip.adjustments.base_salary) }}</td>
              </tr>
              <tr v-if="Number(slip.adjustments?.thirteenth_salary) > 0" class="text-green-700 dark:text-green-400">
                <td class="py-2">{{ t('thirteenth_salary') }}</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(slip.adjustments.thirteenth_salary) }}</td>
                <td class="py-2 text-right">—</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(slip.adjustments.thirteenth_salary) }}</td>
              </tr>
              <tr v-if="Number(slip.adjustments?.unpaid_leave_amount) > 0" class="text-red-700 dark:text-red-400">
                <td class="py-2">{{ t('unpaid_leave') }}</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(-slip.adjustments.unpaid_leave_amount) }}</td>
                <td class="py-2 text-right">—</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(-slip.adjustments.unpaid_leave_amount) }}</td>
              </tr>
              <tr v-if="Number(slip.adjustments?.reimbursement_amount) > 0" class="text-green-700 dark:text-green-400">
                <td class="py-2">{{ t('expense_reimbursement') }}</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(slip.adjustments.reimbursement_amount) }}</td>
                <td class="py-2 text-right">—</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(slip.adjustments.reimbursement_amount) }}</td>
              </tr>
              <!-- Gross salary -->
              <tr class="font-medium">
                <td class="py-2.5">{{ t('gross_salary') }}</td>
                <td class="py-2.5 text-right font-mono">{{ formatCurrency(slip.gross_salary) }}</td>
                <td class="py-2.5 text-right text-[hsl(var(--muted-foreground))]">—</td>
                <td class="py-2.5 text-right font-mono">{{ formatCurrency(slip.gross_salary) }}</td>
              </tr>
              <!-- Deductions -->
              <tr v-for="d in [
                deductionRow(t('avs_employee'), slip.deductions?.avs_employee, slip.deductions?.avs_employer),
                deductionRow(t('ac_employee'), slip.deductions?.ac_employee, slip.deductions?.ac_employer),
                deductionRow(t('aanp_employee'), slip.deductions?.aanp_employee, slip.deductions?.aanp_employer),
                deductionRow(t('lpp_employee'), slip.deductions?.lpp_employee, slip.deductions?.lpp_employer),
              ]" :key="d.label" class="text-red-700 dark:text-red-400">
                <td class="py-2">{{ d.label }}</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(-d.employee) }}</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(-d.employer) }}</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(-d.total) }}</td>
              </tr>
              <tr v-if="Number(slip.deductions?.source_tax) > 0" class="text-red-700 dark:text-red-400">
                <td class="py-2">{{ t('withholding_tax') }}</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(-slip.deductions.source_tax) }}</td>
                <td class="py-2 text-right">—</td>
                <td class="py-2 text-right font-mono">{{ formatCurrency(-slip.deductions.source_tax) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-[hsl(var(--border))] font-bold text-[hsl(var(--foreground))]">
                <td class="pt-3">{{ t('net_salary') }}</td>
                <td class="pt-3 text-right font-mono text-green-700 dark:text-green-400">
                  {{ formatCurrency(slip.net_salary) }}
                </td>
                <td />
                <td />
              </tr>
            </tfoot>
          </table>
          </div>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex flex-wrap gap-3">
        <Button
          v-if="canManage && slip.status !== 'posted'"
          size="sm"
          :disabled="postForm.processing"
          @click="postToLedger"
        >
          {{ t('post_to_ledger') }}
        </Button>
        <Button
          v-if="canManage && slip.status === 'posted'"
          variant="outline"
          size="sm"
          @click="showUnpostDialog = true"
        >
          {{ t('unpost') }}
        </Button>
        <Button
          v-if="canManage && slip.status !== 'posted'"
          variant="outline"
          size="sm"
          class="text-[hsl(var(--destructive))]"
          @click="showDeleteDialog = true"
        >
          {{ t('delete') }}
        </Button>
        <Button variant="outline" size="sm" @click="downloadPdf">
          {{ t('download_pdf') }}
        </Button>
        <p v-if="slip.status === 'posted'" class="flex items-center text-sm text-green-700 dark:text-green-400">
          {{ t('slip_posted_to_ledger') }}
          <span v-if="canManage && slip.journal_entry_id" class="ml-2">
            (<Link :href="`/accounting/journal-entries/${slip.journal_entry_id}`" class="hover:underline">#{{ slip.journal_entry_id }}</Link>)
          </span>
        </p>
      </div>

      <p v-if="postForm.hasErrors" class="text-sm text-[hsl(var(--destructive))]">
        {{ Object.values(postForm.errors).join(', ') }}
      </p>
    </div>

    <ConfirmDialog
      :open="showUnpostDialog"
      :title="t('unpost')"
      :message="t('unpost_salary_slip_confirm')"
      :confirm-label="t('unpost')"
      :processing="unposting"
      @confirm="unpost"
      @cancel="showUnpostDialog = false"
    />

    <ConfirmDialog
      :open="showDeleteDialog"
      :title="t('delete')"
      :message="t('delete_salary_slip_confirm')"
      :confirm-label="t('delete')"
      :processing="deleting"
      @confirm="executeDelete"
      @cancel="showDeleteDialog = false"
    />
  </AppLayout>
</template>
