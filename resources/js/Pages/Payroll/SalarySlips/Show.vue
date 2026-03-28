<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  slip: Object,
})

const postForm = useForm({})

function postToLedger() {
  postForm.post(`/payroll/salary-slips/${props.slip.id}/post`)
}

function downloadPdf() {
  window.open(`/payroll/salary-slips/${props.slip.id}/pdf`, '_blank')
}

function formatSwiss(v) {
  if (v == null) return '—'
  return Number(v).toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
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
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>{{ t('salary_slip') }} — {{ slip.month_label }}</CardTitle>
              <p class="mt-1 text-sm text-[hsl(var(--muted-foreground))]">
                <Link :href="`/payroll/employees/${slip.employee_id}`" class="hover:underline">
                  {{ slip.employee_name }}
                </Link>
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
              <!-- Gross salary -->
              <tr class="font-medium">
                <td class="py-2.5">{{ t('gross_salary') }}</td>
                <td class="py-2.5 text-right font-mono">CHF {{ formatSwiss(slip.gross_salary) }}</td>
                <td class="py-2.5 text-right text-[hsl(var(--muted-foreground))]">—</td>
                <td class="py-2.5 text-right font-mono">CHF {{ formatSwiss(slip.gross_salary) }}</td>
              </tr>
              <!-- Deductions -->
              <tr v-for="d in [
                deductionRow(t('avs_employee'), slip.avs_employee, slip.avs_employer),
                deductionRow(t('ac_employee'), slip.ac_employee, slip.ac_employer),
                deductionRow(t('aanp_employee'), slip.aanp_employee, slip.aanp_employer),
                deductionRow(t('lpp_employee'), slip.lpp_employee, slip.lpp_employer),
              ]" :key="d.label" class="text-red-700 dark:text-red-400">
                <td class="py-2">{{ d.label }}</td>
                <td class="py-2 text-right font-mono">−CHF {{ formatSwiss(d.employee) }}</td>
                <td class="py-2 text-right font-mono">−CHF {{ formatSwiss(d.employer) }}</td>
                <td class="py-2 text-right font-mono">−CHF {{ formatSwiss(d.total) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-[hsl(var(--border))] font-bold text-[hsl(var(--foreground))]">
                <td class="pt-3">{{ t('net_salary') }}</td>
                <td class="pt-3 text-right font-mono text-green-700 dark:text-green-400">
                  CHF {{ formatSwiss(slip.net_salary) }}
                </td>
                <td />
                <td />
              </tr>
            </tfoot>
          </table>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex gap-3">
        <Button
          v-if="slip.status !== 'posted'"
          :disabled="postForm.processing"
          @click="postToLedger"
        >
          {{ t('post_to_ledger') }}
        </Button>
        <Button variant="outline" @click="downloadPdf">
          {{ t('download_pdf') }}
        </Button>
        <p v-if="slip.status === 'posted'" class="flex items-center text-sm text-green-700 dark:text-green-400">
          {{ t('slip_posted_to_ledger') }}
          <span v-if="slip.journal_entry_id" class="ml-2">
            (<Link :href="`/accounting/journal-entries/${slip.journal_entry_id}`" class="hover:underline">#{{ slip.journal_entry_id }}</Link>)
          </span>
        </p>
      </div>

      <p v-if="postForm.hasErrors" class="text-sm text-[hsl(var(--destructive))]">
        {{ Object.values(postForm.errors).join(', ') }}
      </p>
    </div>
  </AppLayout>
</template>
