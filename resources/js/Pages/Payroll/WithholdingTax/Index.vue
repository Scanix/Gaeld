<script setup>
import AppLayout from '@/Components/AppLayout.vue'
import Card from '@/Components/UI/Card.vue'
import CardHeader from '@/Components/UI/CardHeader.vue'
import CardTitle from '@/Components/UI/CardTitle.vue'
import CardContent from '@/Components/UI/CardContent.vue'
import Badge from '@/Components/UI/Badge.vue'
import Button from '@/Components/UI/Button.vue'
import DataTable from '@/Components/UI/DataTable.vue'
import Modal from '@/Components/UI/Modal.vue'
import FormInput from '@/Components/UI/FormInput.vue'
import FormSelect from '@/Components/UI/FormSelect.vue'
import { useTranslations } from '@/lib/useTranslations'
import { useFormatters } from '@/lib/useFormatters'
import { computed, ref } from 'vue'
import { router, useForm, Link } from '@inertiajs/vue3'
import { Plus, Send, FileText } from 'lucide-vue-next'

const props = defineProps({
  declarations: Array,
})

const { t } = useTranslations()
const { formatCurrency } = useFormatters()

const statusVariant = { draft: 'default', submitted: 'info', paid: 'success' }

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

const columns = computed(() => [
  { key: 'canton', label: t('source_tax_canton') },
  { key: 'declaration_period', label: t('withholding_declaration_period') },
  { key: 'total_tax', label: t('withholding_total_tax'), class: 'text-right', format: v => formatCurrency(v) },
  { key: 'status', label: t('status') },
  { key: 'submitted_at', label: t('date') },
  { key: 'actions', label: '', sortable: false },
])

// Create dialog
const showForm = ref(false)
const form = useForm({
  canton: '',
  period: '',
})

function openCreate() {
  form.reset()
  form.clearErrors()
  showForm.value = true
}

function submitForm() {
  form.post('/payroll/withholding-tax/declarations', {
    preserveScroll: true,
    onSuccess: () => { showForm.value = false },
  })
}

// Submit declaration
function submitDeclaration(declaration) {
  router.post(`/payroll/withholding-tax/declarations/${declaration.id}/submit`, {}, {
    preserveScroll: true,
  })
}
</script>

<template>
  <AppLayout :title="t('withholding_tax')">
    <div class="mb-4 flex items-center justify-between">
      <div />
      <Link href="/payroll/withholding-tax/tariffs">
        <Button variant="outline" size="sm">
          <FileText class="mr-1 h-4 w-4" /> {{ t('withholding_tax_tariffs') }}
        </Button>
      </Link>
    </div>

    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>{{ t('withholding_tax_declarations') }}</CardTitle>
          <Button size="sm" @click="openCreate">
            <Plus class="mr-1 h-4 w-4" /> {{ t('add') }}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <p class="mb-4 text-sm text-[hsl(var(--muted-foreground))]">{{ t('withholding_tax_desc') }}</p>
        <DataTable :columns="columns" :rows="declarations">
          <template #cell-status="{ value }">
            <Badge :variant="statusVariant[value] || 'default'">
              {{ t(`withholding_status_${value}`) }}
            </Badge>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex items-center gap-1 justify-end">
              <Button
                v-if="row.status === 'draft'"
                variant="ghost"
                size="sm"
                @click="submitDeclaration(row)"
              >
                <Send class="mr-1 h-4 w-4" /> {{ t('submit') }}
              </Button>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Modal :open="showForm" :title="t('withholding_tax')" @close="showForm = false">
      <form class="space-y-6" @submit.prevent="submitForm">
        <FormSelect
          id="canton"
          v-model="form.canton"
          :label="t('source_tax_canton')"
          :options="cantonOptions"
          :error="form.errors.canton"
          required
        />
        <FormInput
          id="period"
          v-model="form.period"
          :label="t('withholding_declaration_period')"
          :error="form.errors.period"
          placeholder="2026-03"
          required
        />
        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="showForm = false">{{ t('cancel') }}</Button>
          <Button type="submit" :disabled="form.processing" :loading="form.processing">{{ t('save') }}</Button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>
