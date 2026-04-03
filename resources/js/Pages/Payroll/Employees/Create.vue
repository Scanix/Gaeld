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
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslations } from '@/lib/useTranslations'
import { HelpCircle } from 'lucide-vue-next'

const { t } = useTranslations()

const statusOptions = [
  { value: 'active', label: t('employee_status_active') },
  { value: 'inactive', label: t('employee_status_inactive') },
]

const form = useForm({
  first_name: '',
  last_name: '',
  ahv_number: '',
  email: '',
  phone: '',
  position: '',
  start_date: new Date().toISOString().slice(0, 10),
  gross_salary: '',
  status: 'active',
  iban: '',
})

function submit() {
  form.transform((data) => ({
    first_name: data.first_name,
    last_name: data.last_name,
    email: data.email || null,
    ahv_number: data.ahv_number || null,
    entry_date: data.start_date,
    gross_salary: data.gross_salary,
    is_active: data.status === 'active',
  })).post('/payroll/employees')
}
</script>

<template>
  <AppLayout :title="t('new_employee')" help-page="payroll">
    <Breadcrumb
      :items="[{ label: t('payroll'), href: '/payroll/employees' }, { label: t('employees'), href: '/payroll/employees' }, { label: t('new_employee') }]"
      class="mb-4"
    />

    <Card class="max-w-2xl">
      <CardHeader>
        <CardTitle>{{ t('new_employee') }}</CardTitle>
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

          <!-- AHV Number (masked: 756.XXXX.XXXX.XX) -->
          <div class="relative">
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
            <Tooltip :content="t('tooltip_ahv_number')" side="top" class="absolute right-0 top-0">
              <HelpCircle class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))]" />
            </Tooltip>
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
              id="start_date"
              v-model="form.start_date"
              type="date"
              :label="t('start_date')"
              :error="form.errors.start_date"
              required
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
              v-model="form.status"
              :label="t('status')"
              :options="statusOptions"
              :error="form.errors.status"
            />
          </div>

          <!-- Bank account -->
          <hr class="border-[hsl(var(--border))]" />
          <h3 class="text-sm font-medium text-[hsl(var(--foreground))]">{{ t('bank_account') }}</h3>
          <div class="relative">
            <MaskedInput
              id="iban"
              v-model="form.iban"
              mask="iban"
              :label="t('iban')"
              :error="form.errors.iban"
              placeholder="CH56 0483 5012 3456 7800 9"
            />
            <Tooltip :content="t('tooltip_iban')" side="top" class="absolute right-0 top-0">
              <HelpCircle class="h-3.5 w-3.5 text-[hsl(var(--muted-foreground))]" />
            </Tooltip>
          </div>

          <div class="flex flex-wrap justify-end gap-3">
            <Button type="button" variant="outline" as="a" href="/payroll/employees">
              {{ t('cancel') }}
            </Button>
            <Button type="submit" :disabled="form.processing">
              {{ t('create_employee') }}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  </AppLayout>
</template>
