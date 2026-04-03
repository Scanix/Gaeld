<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import Badge from '@/Components/UI/Badge.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { computed } from 'vue'
import { Pencil } from 'lucide-vue-next'

const { t } = useTranslations()
const { formatDate, formatCurrency } = useFormatters()

const props = defineProps({
  employee: Object,
  salarySlips: { type: Array, default: () => [] },
})



const salaryColumns = computed(() => [
  { key: 'period', label: t('period') },
  { key: 'gross_salary', label: t('gross_salary'), class: 'text-right' },
  { key: 'net_salary', label: t('net_salary'), class: 'text-right' },
  { key: 'status', label: t('status') },
  { key: 'actions', label: '', class: 'text-right w-20' },
])
</script>

<template>
  <AppLayout :title="`${employee.first_name} ${employee.last_name}`" help-page="payroll">
    <Breadcrumb
      :items="[{ label: t('payroll'), href: '/payroll/employees' }, { label: t('employees'), href: '/payroll/employees' }, { label: `${employee.first_name} ${employee.last_name}` }]"
      class="mb-4"
    />

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Employee details -->
      <Card class="lg:col-span-2">
        <CardHeader>
          <div class="flex flex-wrap items-center justify-between gap-3">
            <CardTitle>{{ employee.first_name }} {{ employee.last_name }}</CardTitle>
            <div class="flex flex-wrap items-center gap-2">
              <Badge :variant="employee.status === 'active' ? 'default' : 'secondary'">
                {{ t('employee_status_' + employee.status) }}
              </Badge>
              <Button variant="outline" size="sm" as="a" :href="`/payroll/employees/${employee.id}/edit`">
                <Pencil class="mr-2 h-4 w-4" />
                {{ t('edit') }}
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('email') }}</p>
              <p class="font-medium">{{ employee.email ?? '—' }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('phone') }}</p>
              <p class="font-medium">{{ employee.phone ?? '—' }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('ahv_number') }}</p>
              <p class="font-medium font-mono tracking-wider">{{ employee.ahv_number }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('position') }}</p>
              <p class="font-medium">{{ employee.position ?? '—' }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('start_date') }}</p>
              <p class="font-medium">{{ formatDate(employee.start_date) }}</p>
            </div>
            <div>
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('gross_salary') }}</p>
              <p class="font-medium font-mono">{{ formatCurrency(employee.gross_salary) }}</p>
            </div>
            <div v-if="employee.iban" class="col-span-2">
              <p class="text-[hsl(var(--muted-foreground))]">{{ t('iban') }}</p>
              <p class="font-medium font-mono">{{ employee.iban }}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Quick actions -->
      <Card>
        <CardHeader>
          <CardTitle>{{ t('actions') }}</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col gap-3">
          <Button as="a" href="/payroll/salary-slips" class="w-full" variant="outline">
            {{ t('view_salary_slips') }}
          </Button>
          <Button as="a" href="/payroll/run" class="w-full">
            {{ t('run_payroll') }}
          </Button>
        </CardContent>
      </Card>
    </div>

    <!-- Salary history -->
    <Card>
      <CardHeader>
        <CardTitle>{{ t('salary_history') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <DataTable
          :columns="salaryColumns"
          :rows="salarySlips"
          :pagination="null"
        >
          <template #cell-period="{ row }">
            {{ row.month_label }}
          </template>
          <template #cell-gross_salary="{ row }">
            <span class="font-mono">{{ formatCurrency(row.gross_salary) }}</span>
          </template>
          <template #cell-net_salary="{ row }">
            <span class="font-mono">{{ formatCurrency(row.net_salary) }}</span>
          </template>
          <template #cell-status="{ row }">
            <Badge :variant="row.status === 'posted' ? 'default' : 'secondary'">
              {{ t('slip_status_' + row.status) }}
            </Badge>
          </template>
          <template #cell-actions="{ row }">
            <Link :href="`/payroll/salary-slips/${row.id}`" class="text-[hsl(var(--primary))] hover:underline text-sm">
              {{ t('view') }}
            </Link>
          </template>
        </DataTable>
        <p v-if="!salarySlips.length" class="py-8 text-center text-sm text-[hsl(var(--muted-foreground))]">
          {{ t('no_salary_slips') }}
        </p>
      </CardContent>
    </Card>
  </AppLayout>
</template>
