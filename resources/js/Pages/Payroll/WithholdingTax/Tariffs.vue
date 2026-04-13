<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { computed, ref } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  tariffs: Array,
})

const { t } = useTranslations()

const cantonOptions = [
  { value: '', label: t('select_placeholder') },
  { value: 'AG', label: 'AG' }, { value: 'AI', label: 'AI' },
  { value: 'AR', label: 'AR' }, { value: 'BE', label: 'BE' },
  { value: 'BL', label: 'BL' }, { value: 'BS', label: 'BS' },
  { value: 'FR', label: 'FR' }, { value: 'GE', label: 'GE' },
  { value: 'GL', label: 'GL' }, { value: 'GR', label: 'GR' },
  { value: 'JU', label: 'JU' }, { value: 'LU', label: 'LU' },
  { value: 'NE', label: 'NE' }, { value: 'NW', label: 'NW' },
  { value: 'OW', label: 'OW' }, { value: 'SG', label: 'SG' },
  { value: 'SH', label: 'SH' }, { value: 'SO', label: 'SO' },
  { value: 'SZ', label: 'SZ' }, { value: 'TG', label: 'TG' },
  { value: 'TI', label: 'TI' }, { value: 'UR', label: 'UR' },
  { value: 'VD', label: 'VD' }, { value: 'VS', label: 'VS' },
  { value: 'ZG', label: 'ZG' }, { value: 'ZH', label: 'ZH' },
]

const tariffCodeOptions = [
  { value: '', label: t('select_placeholder') },
  { value: 'A', label: 'A' }, { value: 'B', label: 'B' },
  { value: 'C', label: 'C' }, { value: 'D', label: 'D' },
  { value: 'E', label: 'E' }, { value: 'F', label: 'F' },
  { value: 'G', label: 'G' }, { value: 'H', label: 'H' },
]

const columns = computed(() => [
  { key: 'canton', label: t('source_tax_canton') },
  { key: 'tariff_code', label: t('source_tax_tariff') },
  { key: 'income_from', label: t('income_from'), class: 'text-right' },
  { key: 'income_to', label: t('income_to'), class: 'text-right' },
  { key: 'monthly_rate', label: t('monthly_rate'), class: 'text-right', format: v => `${v}%` },
  { key: 'valid_from', label: t('valid_from') },
  { key: 'actions', label: '', sortable: false },
])

// Create dialog
const showForm = ref(false)
const form = useForm({
  canton: '',
  tariff_code: '',
  income_from: '',
  income_to: '',
  monthly_rate: '',
  resident: true,
  church_tax: false,
  valid_from: '',
})

function openCreate() {
  form.reset()
  form.clearErrors()
  showForm.value = true
}

function submitForm() {
  form.post('/payroll/withholding-tax/tariffs', {
    preserveScroll: true,
    onSuccess: () => { showForm.value = false },
  })
}

// Delete
const showDelete = ref(false)
const deletingTariff = ref(null)
const deleteProcessing = ref(false)

function confirmDelete(tariff) {
  deletingTariff.value = tariff
  showDelete.value = true
}

function performDelete() {
  if (!deletingTariff.value) return
  deleteProcessing.value = true
  router.delete(`/payroll/withholding-tax/tariffs/${deletingTariff.value.id}`, {
    preserveScroll: true,
    onFinish: () => {
      deleteProcessing.value = false
      showDelete.value = false
      deletingTariff.value = null
    },
  })
}
</script>

<template>
  <AppLayout :title="t('withholding_tax_tariffs')">
    <div class="mb-4">
      <Link href="/payroll/withholding-tax" class="inline-flex items-center gap-1 text-sm text-[hsl(var(--muted-foreground))] hover:text-[hsl(var(--foreground))]">
        <ArrowLeft class="h-4 w-4" /> {{ t('back') }}
      </Link>
    </div>

    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>{{ t('withholding_tax_tariffs') }}</CardTitle>
          <Button size="sm" @click="openCreate">
            <Plus class="mr-1 h-4 w-4" /> {{ t('add') }}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <DataTable :columns="columns" :rows="tariffs">
          <template #cell-actions="{ row }">
            <div class="flex items-center gap-1 justify-end">
              <Button variant="ghost" size="icon" @click="confirmDelete(row)">
                <Trash2 class="h-4 w-4 text-[hsl(var(--destructive))]" />
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Modal :open="showForm" :title="t('withholding_tax_tariffs')" @close="showForm = false">
      <form class="space-y-6" @submit.prevent="submitForm">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormSelect id="canton" v-model="form.canton" :label="t('source_tax_canton')" :options="cantonOptions" :error="form.errors.canton" required />
          <FormSelect id="tariff_code" v-model="form.tariff_code" :label="t('source_tax_tariff')" :options="tariffCodeOptions" :error="form.errors.tariff_code" required />
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormInput id="income_from" v-model="form.income_from" type="number" step="0.01" :label="t('income_from')" :error="form.errors.income_from" required />
          <FormInput id="income_to" v-model="form.income_to" type="number" step="0.01" :label="t('income_to')" :error="form.errors.income_to" />
        </div>
        <FormInput id="monthly_rate" v-model="form.monthly_rate" type="number" step="0.01" :label="t('monthly_rate')" :error="form.errors.monthly_rate" required />
        <div class="flex gap-6">
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.resident" type="checkbox" class="h-4 w-4 rounded border-[hsl(var(--input))]" />
            {{ t('resident') }}
          </label>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.church_tax" type="checkbox" class="h-4 w-4 rounded border-[hsl(var(--input))]" />
            {{ t('church_tax') }}
          </label>
        </div>
        <FormInput id="valid_from" v-model="form.valid_from" type="date" :label="t('valid_from')" :error="form.errors.valid_from" required />
        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="showForm = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing" :loading="form.processing">{{ t('save') }}</Button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog
      :open="showDelete"
      :title="t('confirm_delete')"
      :message="deletingTariff ? `${deletingTariff.canton} ${deletingTariff.tariff_code}` : ''"
      :processing="deleteProcessing"
      @confirm="performDelete"
      @cancel="showDelete = false"
    />
  </AppLayout>
</template>
