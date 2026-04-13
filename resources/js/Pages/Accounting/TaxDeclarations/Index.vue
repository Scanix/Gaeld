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
import EmptyState from '@/Components/UI/EmptyState.vue'
import { computed, ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { Plus, Eye, FileText } from 'lucide-vue-next'

const props = defineProps({
  declarations: Array,
})

const { t } = useTranslations()

const statusVariant = {
  draft: 'default',
  finalized: 'success',
  submitted: 'info',
}

const cantonOptions = [
  { value: '', label: t('select_placeholder') },
  { value: 'AG', label: 'Aargau' }, { value: 'AI', label: 'Appenzell I.Rh.' },
  { value: 'AR', label: 'Appenzell A.Rh.' }, { value: 'BE', label: 'Bern' },
  { value: 'BL', label: 'Basel-Land' }, { value: 'BS', label: 'Basel-Stadt' },
  { value: 'FR', label: 'Fribourg' }, { value: 'GE', label: 'Genève' },
  { value: 'GL', label: 'Glarus' }, { value: 'GR', label: 'Graubünden' },
  { value: 'JU', label: 'Jura' }, { value: 'LU', label: 'Luzern' },
  { value: 'NE', label: 'Neuchâtel' }, { value: 'NW', label: 'Nidwalden' },
  { value: 'OW', label: 'Obwalden' }, { value: 'SG', label: 'St. Gallen' },
  { value: 'SH', label: 'Schaffhausen' }, { value: 'SO', label: 'Solothurn' },
  { value: 'SZ', label: 'Schwyz' }, { value: 'TG', label: 'Thurgau' },
  { value: 'TI', label: 'Ticino' }, { value: 'UR', label: 'Uri' },
  { value: 'VD', label: 'Vaud' }, { value: 'VS', label: 'Valais' },
  { value: 'ZG', label: 'Zug' }, { value: 'ZH', label: 'Zürich' },
]

const columns = computed(() => [
  { key: 'fiscal_year', label: t('tax_declaration_fiscal_year') },
  { key: 'canton', label: t('tax_declaration_canton') },
  { key: 'status', label: t('status') },
  { key: 'finalized_at', label: t('date') },
  { key: 'actions', label: '', sortable: false },
])

// Create dialog
const showForm = ref(false)
const form = useForm({
  fiscal_year: new Date().getFullYear(),
  canton: '',
})

function openCreate() {
  form.reset()
  form.clearErrors()
  showForm.value = true
}

function submitForm() {
  form.post('/accounting/tax-declarations', {
    preserveScroll: true,
    onSuccess: () => { showForm.value = false },
  })
}
</script>

<template>
  <AppLayout :title="t('tax_declarations')">
    <Card>
      <CardHeader>
        <div class="flex items-center justify-between">
          <CardTitle>{{ t('tax_declarations') }}</CardTitle>
          <Button size="sm" @click="openCreate">
            <Plus class="mr-1 h-4 w-4" /> {{ t('add') }}
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <p class="mb-4 text-sm text-[hsl(var(--muted-foreground))]">{{ t('tax_declaration_desc') }}</p>
        <DataTable :columns="columns" :rows="declarations">
          <template #empty>
            <EmptyState
              :icon="FileText"
              :title="t('empty_tax_declarations_title')"
              :description="t('empty_tax_declarations_desc')"
              :action-label="t('create_first')"
              @action="openCreate"
            />
          </template>
          <template #cell-status="{ value }">
            <Badge :variant="statusVariant[value] || 'default'">
              {{ t(`tax_declaration_status_${value}`) }}
            </Badge>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex items-center gap-1 justify-end">
              <Link :href="`/accounting/tax-declarations/${row.id}`">
                <Button variant="ghost" size="icon">
                  <Eye class="h-4 w-4" />
                </Button>
              </Link>
            </div>
          </template>
        </DataTable>
      </CardContent>
    </Card>

    <Modal :open="showForm" :title="t('tax_declaration')" @close="showForm = false">
      <form class="space-y-6" @submit.prevent="submitForm">
        <FormInput
          id="fiscal_year"
          v-model="form.fiscal_year"
          type="number"
          :label="t('tax_declaration_fiscal_year')"
          :error="form.errors.fiscal_year"
          required
        />
        <FormSelect
          id="canton"
          v-model="form.canton"
          :label="t('tax_declaration_canton')"
          :options="cantonOptions"
          :error="form.errors.canton"
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
