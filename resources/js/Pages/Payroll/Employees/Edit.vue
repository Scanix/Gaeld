<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import MaskedInput from '@/Components/UI/MaskedInput.vue'
import Breadcrumb from '@/Components/UI/Breadcrumb.vue'
import { useTranslations } from '@/lib/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  employee: Object,
})

const statusOptions = [
  { value: true, label: t('employee_status_active') },
  { value: false, label: t('employee_status_inactive') },
]

const form = useForm({
  first_name: props.employee.first_name ?? '',
  last_name: props.employee.last_name ?? '',
  ahv_number: props.employee.ahv_number ?? '',
  email: props.employee.email ?? '',
  phone: props.employee.phone ?? '',
  position: props.employee.position ?? '',
  entry_date: props.employee.entry_date ?? '',
  exit_date: props.employee.exit_date ?? '',
  gross_salary: props.employee.gross_salary ?? '',
  is_active: props.employee.is_active ?? true,
  is_source_tax_subject: props.employee.is_source_tax_subject ?? false,
  iban: props.employee.iban ?? '',
})

function submit() {
  form.put(`/payroll/employees/${props.employee.id}`)
}
</script>

<template>
  <AppLayout :title="t('edit_employee')" help-page="payroll">
    <Breadcrumb
      :items="[{ label: t('payroll'), href: '/payroll/employees' }, { label: t('employees'), href: '/payroll/employees' }, { label: `${employee.first_name} ${employee.last_name}`, href: `/payroll/employees/${employee.id}` }, { label: t('edit') }]"
      class="mb-4"
    />

    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('edit_employee') }}</CardTitle>
      </CardHeader>
      <CardContent>
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Personal info -->
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('personal_info') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormInput
              id="first_name"
              v-model="form.first_name"
              :label="t('first_name')"
              :error="form.errors.first_name"
              required
            />
            <FormInput
              id="last_name"
              v-model="form.last_name"
              :label="t('last_name')"
              :error="form.errors.last_name"
              required
            />
            <FormInput
              id="email"
              v-model="form.email"
              type="email"
              :label="t('email')"
              :error="form.errors.email"
            />
            <FormInput
              id="phone"
              v-model="form.phone"
              type="tel"
              :label="t('phone')"
              :error="form.errors.phone"
            />
          </div>

          <!-- AHV Number -->
          <div>
            <label class="mb-1.5 block text-sm font-medium">
              {{ t('ahv_number') }}
              <span class="text-[hsl(var(--destructive))]">*</span>
            </label>
            <input
              id="ahv_number"
              v-model="form.ahv_number"
              type="text"
              placeholder="756.XXXX.XXXX.XX"
              pattern="\d{3}\.\d{4}\.\d{4}\.\d{2}"
              maxlength="16"
              required
              class="flex h-10 w-full rounded-md border border-[hsl(var(--input))] bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-[hsl(var(--muted-foreground))] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[hsl(var(--ring))] disabled:cursor-not-allowed disabled:opacity-50 sm:h-9 font-mono tracking-wider"
            />
            <p class="mt-1 text-xs text-[hsl(var(--muted-foreground))]">{{ t('ahv_format_hint') }}</p>
            <p v-if="form.errors.ahv_number" class="mt-1 text-xs text-[hsl(var(--destructive))]">{{ form.errors.ahv_number }}</p>
          </div>

          <!-- Employment -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('employment') }}</h3>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormInput
              id="position"
              v-model="form.position"
              :label="t('position')"
              :error="form.errors.position"
            />
            <FormInput
              id="entry_date"
              v-model="form.entry_date"
              type="date"
              :label="t('start_date')"
              :error="form.errors.entry_date"
              required
            />
            <FormInput
              id="exit_date"
              v-model="form.exit_date"
              type="date"
              :label="t('exit_date')"
              :error="form.errors.exit_date"
            />
            <FormInput
              id="gross_salary"
              v-model="form.gross_salary"
              type="number"
              step="0.01"
              min="0"
              :label="t('gross_salary') + ' (CHF' + t('per_month') + ')'"
              :error="form.errors.gross_salary"
              required
            />
            <FormSelect
              id="status"
              v-model="form.is_active"
              :label="t('status')"
              :options="statusOptions"
              :error="form.errors.is_active"
            />
          </div>

          <!-- Bank account -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('bank_account') }}</h3>
          <MaskedInput
            id="iban"
            v-model="form.iban"
            mask="iban"
            :label="t('iban')"
            :error="form.errors.iban"
            placeholder="CH56 0483 5012 3456 7800 9"
          />

          <div class="flex justify-end gap-3">
            <Button type="button" variant="outline" as="a" :href="`/payroll/employees/${employee.id}`">
              {{ t('cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
              {{ t('save_changes') }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
